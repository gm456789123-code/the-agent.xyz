import { Router } from "express";

export const ordersRouter = Router();

// WordPress owns checkout, pricing and authorization. Disable the obsolete API.
ordersRouter.use((req, res) => {
  res.status(503).json({ error: "Order service unavailable" });
});
