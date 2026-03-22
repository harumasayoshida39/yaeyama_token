import * as anchor from "@coral-xyz/anchor";
import { Program } from "@coral-xyz/anchor";
import { Keypair, Connection, clusterApiUrl } from "@solana/web3.js";
import { YaeyamaToken } from "../target/types/yaeyama_token";
import * as fs from "fs";

async function createMint() {
  const connection = new Connection(clusterApiUrl("devnet"), "confirmed");
  const wallet = anchor.web3.Keypair.fromSecretKey(
    Buffer.from(JSON.parse(fs.readFileSync("/root/.config/solana/id.json", "utf-8")))
  );
  const provider = new anchor.AnchorProvider(connection, new anchor.Wallet(wallet), {});
  anchor.setProvider(provider);

  const program = anchor.workspace.YaeyamaToken as Program<YaeyamaToken>;
  const mintKeypair = Keypair.generate();

  const tx = await program.methods
    .initialize()
    .accounts({
      mint: mintKeypair.publicKey,
      payer: wallet.publicKey,
    })
    .signers([mintKeypair])
    .rpc();

  console.log("✅ Mint created!");
  console.log("Mint address:", mintKeypair.publicKey.toString());
  console.log("Transaction:", tx);
  
  // Mintアドレスをファイルに保存
  fs.writeFileSync("mint_address.txt", mintKeypair.publicKey.toString());
}

createMint().catch(console.error);
