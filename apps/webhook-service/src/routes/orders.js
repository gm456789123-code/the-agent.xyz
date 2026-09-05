import { Router } from "express";
import { pool } from "../db.js";

export const ordersRouter = Router();

ordersRouter.post("/", async (req, res) => {
  const { productId, productTitle, priceCents, customerContact } = req.body;

  if (!productId || !productTitle || !priceCents || !customerContact) {
    return res.status(400).json({ error: "productId, productTitle, priceCents and customerContact are required" });
  }

  const [result] = await pool.query(
    "INSERT INTO orders (product_id, product_title, price_cents, customer_contact) VALUES (?, ?, ?, ?)",
    [productId, productTitle, priceCents, customerContact]
  );

  const [rows] = await pool.query("SELECT * FROM orders WHERE id = ?", [result.insertId]);
  res.status(201).json(rows[0]);
});

ordersRouter.get("/:id", async (req, res) => {
  const [rows] = await pool.query("SELECT * FROM orders WHERE id = ?", [req.params.id]);
  if (rows.length === 0) return res.status(404).json({ error: "order not found" });
  res.status(200).json(rows[0]);
});
