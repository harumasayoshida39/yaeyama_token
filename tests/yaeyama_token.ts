import * as anchor from "@coral-xyz/anchor";
import { Program } from "@coral-xyz/anchor";
import { YaeyamaToken } from "../target/types/yaeyama_token";
import { Keypair, SystemProgram, SYSVAR_RENT_PUBKEY, PublicKey } from "@solana/web3.js";
import {
  TOKEN_PROGRAM_ID,
  getOrCreateAssociatedTokenAccount,
  getAccount,
} from "@solana/spl-token";
import { assert } from "chai";

describe("yaeyama_token", () => {
  const provider = anchor.AnchorProvider.env();
  anchor.setProvider(provider);
  const program = anchor.workspace.YaeyamaToken as Program<YaeyamaToken>;

  const mintKeypair = Keypair.generate();
  const merchantWallet = Keypair.generate();
  const customerWallet = Keypair.generate();

  it("Creates YAE token mint (decimals=6)", async () => {
    await program.methods
      .initialize()
      .accounts({
        mint: mintKeypair.publicKey,
        payer: provider.wallet.publicKey,
        tokenProgram: TOKEN_PROGRAM_ID,
        systemProgram: SystemProgram.programId,
        rent: SYSVAR_RENT_PUBKEY,
      })
      .signers([mintKeypair])
      .rpc();

    console.log("YAE Mint address:", mintKeypair.publicKey.toBase58());
  });

  it("Mints 1000 YAE to customer", async () => {
    // カスタマーにSOLをエアドロップ
    await provider.connection.requestAirdrop(customerWallet.publicKey, 2_000_000_000);
    await new Promise((r) => setTimeout(r, 1000));

    const tokenAccount = await getOrCreateAssociatedTokenAccount(
      provider.connection,
      (provider.wallet as anchor.Wallet).payer,
      mintKeypair.publicKey,
      customerWallet.publicKey
    );

    await program.methods
      .mintTokens(new anchor.BN(1_000_000_000)) // 1000 YAE
      .accounts({
        mint: mintKeypair.publicKey,
        tokenAccount: tokenAccount.address,
        authority: provider.wallet.publicKey,
        tokenProgram: TOKEN_PROGRAM_ID,
      })
      .rpc();

    const account = await getAccount(provider.connection, tokenAccount.address);
    assert.equal(account.amount.toString(), "1000000000");
    console.log("Customer balance: 1000 YAE");
  });

  it("Registers a merchant", async () => {
    // 店舗にSOLをエアドロップ
    await provider.connection.requestAirdrop(merchantWallet.publicKey, 2_000_000_000);
    await new Promise((r) => setTimeout(r, 1000));

    const [merchantPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("merchant"), merchantWallet.publicKey.toBuffer()],
      program.programId
    );

    await program.methods
      .registerMerchant("石垣島カフェ", 5) // 5%キャッシュバック
      .accounts({
        merchant: merchantPda,
        authority: merchantWallet.publicKey,
        systemProgram: SystemProgram.programId,
      })
      .signers([merchantWallet])
      .rpc();

    const merchantAccount = await program.account.merchantAccount.fetch(merchantPda);
    assert.equal(merchantAccount.name, "石垣島カフェ");
    assert.equal(merchantAccount.cashbackRate, 5);
    assert.equal(merchantAccount.isActive, true);
    console.log("Merchant registered:", merchantAccount.name);
    console.log("Merchant PDA:", merchantPda.toBase58());
  });

  it("Processes QR payment (500 YAE)", async () => {
    const [merchantPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("merchant"), merchantWallet.publicKey.toBuffer()],
      program.programId
    );

    // お客さんのトークンアカウント
    const customerTokenAccount = await getOrCreateAssociatedTokenAccount(
      provider.connection,
      (provider.wallet as anchor.Wallet).payer,
      mintKeypair.publicKey,
      customerWallet.publicKey
    );

    // 店舗のトークンアカウント
    const merchantTokenAccount = await getOrCreateAssociatedTokenAccount(
      provider.connection,
      (provider.wallet as anchor.Wallet).payer,
      mintKeypair.publicKey,
      merchantWallet.publicKey
    );

    // 500 YAE決済
    await program.methods
      .processPayment(new anchor.BN(500_000_000))
      .accounts({
        merchant: merchantPda,
        customerTokenAccount: customerTokenAccount.address,
        merchantTokenAccount: merchantTokenAccount.address,
        customer: customerWallet.publicKey,
        tokenProgram: TOKEN_PROGRAM_ID,
      })
      .signers([customerWallet])
      .rpc();

    const customerAccount = await getAccount(provider.connection, customerTokenAccount.address);
    const merchantAccount = await getAccount(provider.connection, merchantTokenAccount.address);

    assert.equal(customerAccount.amount.toString(), "500000000"); // 1000 - 500
    assert.equal(merchantAccount.amount.toString(), "500000000");
    console.log("Customer balance after payment: 500 YAE");
    console.log("Merchant balance after payment: 500 YAE");

    // 売上確認
    const merchantData = await program.account.merchantAccount.fetch(merchantPda);
    console.log("Total sales:", merchantData.totalSales.toString(), "(500 YAE)");
  });
});