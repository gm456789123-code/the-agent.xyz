import { Router } from "express";

export const webhookRouter = Router();

// Stub: real slip OCR/bank-API verification is a follow-up task.
webhookRouter.post("/payment-slip", (req, res) => {
  console.log("[webhook] payment-slip received:", req.body);
  res.status(200).json({ received: true });
});

// Stub: real-time notification fan-out is a follow-up task.
webhookRouter.post("/notify", (req, res) => {
  console.log("[webhook] notify received:", req.body);
  res.status(200).json({ received: true });
});
