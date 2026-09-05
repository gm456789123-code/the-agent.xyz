import express from "express";
import { webhookRouter } from "./routes/webhook.js";
import { ordersRouter } from "./routes/orders.js";

const app = express();
const port = process.env.PORT || 4000;

app.use(express.json());

app.get("/health", (req, res) => {
  res.status(200).json({ status: "ok" });
});

app.use("/webhooks", webhookRouter);
app.use("/orders", ordersRouter);

app.listen(port, () => {
  console.log(`webhook-service listening on port ${port}`);
});
