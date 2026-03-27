<?php
/**
 * Solana SPL Token Transfer Helper
 * PHPからオンチェーンでYAEを送金する
 */
require_once __DIR__ . '/../config.php';

// ============================
// Base58 エンコード/デコード
// ============================
function b58_decode($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $leading = 0;
    for ($i = 0; $i < strlen($data) && $data[$i] === '1'; $i++) $leading++;
    $num = gmp_init(0);
    for ($i = 0; $i < strlen($data); $i++) {
        $pos = strpos($alphabet, $data[$i]);
        if ($pos === false) throw new Exception('Invalid base58: ' . $data[$i]);
        $num = gmp_add(gmp_mul($num, 58), $pos);
    }
    $result = '';
    while (gmp_cmp($num, 0) > 0) {
        list($num, $rem) = gmp_div_qr($num, 256);
        $result = chr(gmp_intval($rem)) . $result;
    }
    return str_repeat("\x00", $leading) . $result;
}

function b58_encode($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $leading = 0;
    for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) $leading++;
    $num = gmp_import($data);
    $result = '';
    while (gmp_cmp($num, 0) > 0) {
        list($num, $rem) = gmp_div_qr($num, 58);
        $result = $alphabet[gmp_intval($rem)] . $result;
    }
    return str_repeat('1', $leading) . $result;
}

// ============================
// Compact-u16 エンコード
// ============================
function compact_u16($n) {
    if ($n <= 0x7F) return chr($n);
    if ($n <= 0x3FFF) return chr(($n & 0x7F) | 0x80) . chr(($n >> 7) & 0x7F);
    return chr(($n & 0x7F) | 0x80) . chr((($n >> 7) & 0x7F) | 0x80) . chr(($n >> 14) & 0x3F);
}

// ============================
// Ed25519曲線上の点かチェック
// (PDA導出に使用)
// ============================
function is_on_ed25519_curve($bytes) {
    if (strlen($bytes) !== 32) return false;
    $p = gmp_sub(gmp_pow(2, 255), 19);
    // d = -121665/121666 mod p
    $d = gmp_init('37095705934669439343138083508754565189542113879843219016388785533085940283555');
    $b = array_values(unpack('C*', $bytes));
    $b[31] &= 0x7F;
    $y_hex = '';
    for ($i = 31; $i >= 0; $i--) $y_hex .= sprintf('%02x', $b[$i]);
    $y = gmp_init($y_hex, 16);
    if (gmp_cmp($y, $p) >= 0) return false;
    $y2 = gmp_powm($y, 2, $p);
    $u  = gmp_mod(gmp_sub($y2, 1), $p);
    $v  = gmp_mod(gmp_add(gmp_mul($d, $y2), 1), $p);
    if (gmp_cmp($v, 0) === 0) return gmp_cmp($u, 0) === 0;
    $v_inv = gmp_powm($v, gmp_sub($p, 2), $p);
    $x2    = gmp_mod(gmp_mul($u, $v_inv), $p);
    if (gmp_cmp($x2, 0) === 0) return true;
    $legendre = gmp_powm($x2, gmp_div(gmp_sub($p, 1), 2), $p);
    return gmp_cmp($legendre, 1) === 0;
}

// ============================
// PDA導出
// ============================
function create_program_address($seeds, $program_id_bytes) {
    $h = hash_init('sha256');
    foreach ($seeds as $seed) hash_update($h, $seed);
    hash_update($h, $program_id_bytes);
    hash_update($h, 'ProgramDerivedAddress');
    $hash = hash_final($h, true);
    if (is_on_ed25519_curve($hash)) return false;
    return $hash;
}

function find_program_address($seeds, $program_id_bytes) {
    for ($nonce = 255; $nonce >= 0; $nonce--) {
        $s = $seeds;
        $s[] = chr($nonce);
        $r = create_program_address($s, $program_id_bytes);
        if ($r !== false) return [$r, $nonce];
    }
    return false;
}

// ATA（Associated Token Account）アドレス導出
function get_ata_bytes($owner_bytes, $mint_bytes) {
    $TOKEN_PROGRAM = b58_decode('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');
    $ATA_PROGRAM   = b58_decode('ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL');
    $r = find_program_address([$owner_bytes, $TOKEN_PROGRAM, $mint_bytes], $ATA_PROGRAM);
    return $r ? $r[0] : false;
}

// ============================
// メイン: SPLトークン送金
// ============================
/**
 * @param string $wallet_secret_b58  顧客の秘密鍵 (Base58, 64byte)
 * @param string $owner_b58          顧客のウォレットアドレス (Base58)
 * @param string $dest_wallet_b58    送金先ウォレットアドレス (Base58)
 * @param float  $amount_yae         送金量 (YAE単位)
 * @return array ['success'=>bool, 'signature'=>string|null, 'error'=>string|null]
 */
function solana_transfer_yae($wallet_secret_b58, $owner_b58, $dest_wallet_b58, $amount_yae) {
    try {
        // 鍵のデコード
        $secret_key  = b58_decode($wallet_secret_b58); // 64byte
        $owner_bytes = b58_decode($owner_b58);          // 32byte
        $dest_bytes  = b58_decode($dest_wallet_b58);    // 32byte
        $mint_bytes  = b58_decode(MINT_ADDRESS);        // 32byte

        if (strlen($secret_key) !== 64) return ['success'=>false,'error'=>'秘密鍵が不正です'];
        if (strlen($owner_bytes) !== 32) return ['success'=>false,'error'=>'顧客アドレスが不正です'];
        if (strlen($dest_bytes)  !== 32) return ['success'=>false,'error'=>'送金先アドレスが不正です'];

        // ATA導出
        $source_ata = get_ata_bytes($owner_bytes, $mint_bytes);
        $dest_ata   = get_ata_bytes($dest_bytes,  $mint_bytes);
        if (!$source_ata || !$dest_ata) return ['success'=>false,'error'=>'ATA導出失敗'];

        $source_ata_b58 = b58_encode($source_ata);
        $dest_ata_b58   = b58_encode($dest_ata);

        // 送金先ATAの存在確認
        $dest_info = solanaRPC('getAccountInfo', [$dest_ata_b58, ['encoding'=>'base64']]);
        $dest_ata_exists = isset($dest_info['result']['value']) && $dest_info['result']['value'] !== null;

        // 最新ブロックハッシュ取得
        $bh = solanaRPC('getLatestBlockhash', [['commitment'=>'confirmed']]);
        if (!isset($bh['result']['value']['blockhash'])) {
            return ['success'=>false,'error'=>'blockhash取得失敗'];
        }
        $blockhash = b58_decode($bh['result']['value']['blockhash']);

        // プログラムID
        $TOKEN  = b58_decode('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');
        $ATA_P  = b58_decode('ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL');
        $SYSTEM = str_repeat("\x00", 32);

        // 金額 (decimals=6)
        $raw = intval(round($amount_yae * 1000000));
        $amount_le = pack('V', $raw & 0xFFFFFFFF) . pack('V', 0); // uint64 LE

        if ($dest_ata_exists) {
            // ===== シンプルなSPL Transfer =====
            // アカウント順: [owner(signer,w), source_ata(w), dest_ata(w), TOKEN(r)]
            $accs = [$owner_bytes, $source_ata, $dest_ata, $TOKEN];
            $header = chr(1) . chr(0) . chr(1); // sigs=1, readonly_signed=0, readonly_unsigned=1

            // Transfer命令: program=3, accounts=[1,2,0], data=[3]+amount
            $ix_data = chr(3) . $amount_le;
            $ix = chr(3) . compact_u16(3) . chr(1) . chr(2) . chr(0)
                . compact_u16(strlen($ix_data)) . $ix_data;

        } else {
            // ===== CreateATA + Transfer =====
            // アカウント順: [owner(s,w), source_ata(w), dest_ata(w), dest_wallet(r), mint(r), SYSTEM(r), TOKEN(r), ATA_P(r)]
            $accs = [$owner_bytes, $source_ata, $dest_ata, $dest_bytes, $mint_bytes, $SYSTEM, $TOKEN, $ATA_P];
            $header = chr(1) . chr(0) . chr(5); // readonly_unsigned=5 (indices 3-7)

            // CreateATA命令: program=7, accounts=[0,2,3,4,5,6], data=[]
            $create_ix = chr(7) . compact_u16(6)
                . chr(0) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6)
                . compact_u16(0);

            // Transfer命令: program=6, accounts=[1,2,0], data=[3]+amount
            $ix_data    = chr(3) . $amount_le;
            $transfer_ix = chr(6) . compact_u16(3) . chr(1) . chr(2) . chr(0)
                . compact_u16(strlen($ix_data)) . $ix_data;

            $ix = $create_ix . $transfer_ix;
        }

        // アカウントキー配列を組み立て
        $accs_bytes = compact_u16(count($accs));
        foreach ($accs as $a) $accs_bytes .= $a;

        // メッセージ
        $num_ix = $dest_ata_exists ? 1 : 2;
        $message = $header . $accs_bytes . $blockhash . compact_u16($num_ix) . $ix;

        // 署名 (sodium Ed25519)
        $sig = sodium_crypto_sign_detached($message, $secret_key);

        // トランザクション = [sig数][sig][message]
        $tx = compact_u16(1) . $sig . $message;

        // 送信
        $result = solanaRPC('sendTransaction', [
            base64_encode($tx),
            ['encoding'=>'base64', 'skipPreflight'=>false, 'preflightCommitment'=>'confirmed']
        ]);

        if (isset($result['result'])) {
            return ['success'=>true, 'signature'=>$result['result']];
        }

        $err = $result['error']['message'] ?? json_encode($result['error'] ?? $result);
        return ['success'=>false, 'error'=>$err];

    } catch (Exception $e) {
        return ['success'=>false, 'error'=>$e->getMessage()];
    }
}
