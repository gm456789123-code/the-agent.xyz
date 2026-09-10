import { Router } from "express";

export const webhookRouter = Router();

// Fail closed until a real provider verifies sender, transaction, recipient,
// amount and replay protection. A submitted slip must never imply payment.
webhookRouter.use((req, res) => {
  res.status(503).json({ error: "Webhook service unavailable" });
});
