import { PublicKey } from "@solana/web3.js";
import { encodeURL } from "@solana/pay";
import BigNumber from "bignumber.js";
import * as QRCode from "qrcode";

async function generatePaymentQR() {
  const merchantWallet = new PublicKey("DmmZbwjGq94CFQw4zoXW6F7ZgZgppFBDsZ6GoVZJYbso");
  const yaeToken = new PublicKey("HmjuMcymQDhZ5NJzi9JYzyP4qSMeNdiF3NdPYUS5nR48");

  const url = encodeURL({
    recipient: merchantWallet,
    amount: new BigNumber(500),
    splToken: yaeToken,
    label: "石垣島カフェ",
    message: "ご来店ありがとうございます",
    memo: "payment-001",
  });

  console.log("Solana Pay URL:", url.toString());

  await QRCode.toFile("payment_qr.png", url.toString(), {
    width: 400,
    margin: 2,
  });

  console.log("QR code saved: payment_qr.png");
}

generatePaymentQR().catch(console.error);