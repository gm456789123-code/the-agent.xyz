import { Router } from "express";
import { pool } from "../db.js";

export const webhookRouter = Router();

// Stub: real slip OCR/bank-API verification is a follow-up task.
// For now, any payload with an orderId is treated as "verified" and marks that order paid.
webhookRouter.post("/payment-slip", async (req, res) => {
  console.log("[webhook] payment-slip received:", req.body);

  const { orderId } = req.body;
  if (!orderId) {
    return res.status(400).json({ error: "orderId is required" });
  }

  const [result] = await pool.query("UPDATE orders SET status = 'paid' WHERE id = ?", [orderId]);
  if (result.affectedRows === 0) {
    return res.status(404).json({ error: "order not found" });
  }

  res.status(200).json({ received: true, orderId, status: "paid" });
});

// Stub: real-time notification fan-out is a follow-up task.
webhookRouter.post("/notify", (req, res) => {
  console.log("[webhook] notify received:", req.body);
  res.status(200).json({ received: true });
});
