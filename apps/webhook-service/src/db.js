import mysql from "mysql2/promise";

export const pool = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  port: Number(process.env.DB_PORT) || 3306,
  database: process.env.DB_NAME || "nexus_arcade_app",
  user: process.env.DB_USER || "nexus",
  password: process.env.DB_PASSWORD || "",
});
