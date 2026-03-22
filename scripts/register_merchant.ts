import * as anchor from "@coral-xyz/anchor";
import { Program } from "@coral-xyz/anchor";
import { YaeyamaToken } from "../target/types/yaeyama_token";
import { Keypair, PublicKey, SystemProgram } from "@solana/web3.js";
import * as fs from "fs";

async function registerMerchant() {
  // devnet接続
  const connection = new anchor.web3.Connection(
    "https://api.devnet.solana.com",
    "confirmed"
  );

  // 運営ウォレット（手数料支払い）
  const payerKeypair = Keypair.fromSecretKey(
    Uint8Array.from(
      JSON.parse(fs.readFileSync("/root/.config/solana/id.json", "utf-8"))
    )
  );

  // 店舗ウォレット
  const merchantKeypair = Keypair.fromSecretKey(
    Uint8Array.from(
      JSON.parse(
        fs.readFileSync("/workspace/project/keys/merchant_cafe1.json", "utf-8")
      )
    )
  );

  const wallet = new anchor.Wallet(payerKeypair);
  const provider = new anchor.AnchorProvider(connection, wallet, {
    commitment: "confirmed",
  });
  anchor.setProvider(provider);

  const idl = JSON.parse(
    fs.readFileSync("/workspace/project/target/idl/yaeyama_token.json", "utf-8")
  );
  const programId = new PublicKey("5PAP6AwioCRco33xoFEDSojU6tVbiRg5Bgt8gz5MoTJd");
  const program = new Program(idl, programId, provider);

  // 店舗にSOLを送金（手数料用）
  const tx = new anchor.web3.Transaction().add(
    anchor.web3.SystemProgram.transfer({
      fromPubkey: payerKeypair.publicKey,
      toPubkey: merchantKeypair.publicKey,
      lamports: 10_000_000, // 0.01 SOL
    })
  );
  await provider.sendAndConfirm(tx);
  console.log("SOL送金完了");

  // PDAを計算
  const [merchantPda] = PublicKey.findProgramAddressSync(
    [Buffer.from("merchant"), merchantKeypair.publicKey.toBuffer()],
    programId
  );

  // 店舗登録
  await program.methods
    .registerMerchant("石垣島カフェ１", 5)
    .accounts({
      merchant: merchantPda,
      authority: merchantKeypair.publicKey,
      systemProgram: SystemProgram.programId,
    })
    .signers([merchantKeypair])
    .rpc();

  console.log("店舗登録完了！");
  console.log("店舗名: 石垣島カフェ１");
  console.log("ウォレット:", merchantKeypair.publicKey.toBase58());
  console.log("PDA:", merchantPda.toBase58());
}

registerMerchant().catch(console.error);