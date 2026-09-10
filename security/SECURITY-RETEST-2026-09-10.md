# ผลตรวจซ้ำหลัง Gemini — 10 กันยายน 2026

**รายงานนี้เป็นหลักฐานก่อนแก้:** ดู [ผลการแก้และการทดสอบล่าสุด](SECURITY-FIXES-2026-09-10.md)

ตรวจ commit `7ff456ce740ab765b518dee3e7f4eff3cc4fb59a` หลังผู้ใช้แจ้งว่า Gemini ทำงานเสร็จแล้ว ตอนเริ่มตรวจ working tree สะอาด

**ยังไม่พร้อมปล่อย:** WordPress ตอบ HTTP 500, phpMyAdmin ที่รันอยู่ยังเปิดทุก interface และข้อบกพร่องด้านสิทธิ์ออเดอร์ยังอยู่

รอบนี้แก้เฉพาะสคริปต์ตรวจและรายงาน ไม่แก้โค้ดแอป ไม่ restart/recreate container และไม่เปลี่ยนข้อมูลจริง

## ผลปัจจุบัน

| ประเด็น | สถานะ | หลักฐาน |
| --- | --- | --- |
| WordPress bootstrap | ยังล้มเหลว — ต้องแก้ก่อน | HTTP 500 ที่ API และ PHP fatal จากการโหลด mu-plugins รวม |
| phpMyAdmin จำกัด loopback | แก้ใน Compose แต่ยังไม่เกิดผลกับ container | Compose ระบุ `127.0.0.1`; Docker ยังรายงาน `0.0.0.0:8082` และ `[::]:8082` |
| phpMyAdmin auto-login | ยังอยู่ | GET ไม่มี cookie ได้ authenticated navigation; config ยังเป็น `auth_type=config` |
| ต้องล็อกอินก่อนสร้างออเดอร์ | ยังข้ามได้ใน callback | ชุดจำลองสร้างออเดอร์โดยไม่มี token สำเร็จ |
| ค้นประวัติจากเบอร์โทร | ยังไม่มีการยืนยันเจ้าของ | callback คืนสินค้า ยอด สถานะ และรหัสออเดอร์ของ fixture |
| ซื้อสินค้า draft | ปิดได้ใน callback | คืน `invalid_product` |
| เบอร์เต็มใน recent feed | ปิดกรณี 9 หลักที่เคยพบได้ใน callback | `000000000` ถูกแสดงเป็น `000-xxx-0000` |
| เบอร์เต็มใน tracking | ยังพบ | field ใหม่ `customer_phone` คืน `000000000` ครบ |
| Rate limiter | ทำงานเมื่อ IP คงเดิม แต่ข้ามได้ด้วย header ใน fixture | ครั้งที่ 6 ถูกบล็อก; เปลี่ยน CF/XFF header โดย REMOTE_ADDR เดิมแล้วผ่าน |
| CORS สำหรับ logout | แก้ชื่อ header ใน source แล้ว | มี `X-Nexus-Token`; ยังยืนยัน live preflight/logout ไม่ได้เพราะ backend 500 |
| Node payment/pricing/order privacy | ช่องโหว่เดิมทำซ้ำได้ | unsigned paid=200; ราคา 1/-100=201; anonymous GET คืน customer_contact |

หลักฐาน callback ใช้โค้ดล่าสุด แต่แทน WordPress/ฐานข้อมูลด้วยข้อมูลจำลอง ไม่ถือเป็นผลทดสอบ live end-to-end ขณะ WordPress ล่ม Node router ทดสอบผ่าน HTTP loopback ชั่วคราวกับ database substitute; บริการจริงที่พอร์ต 4000 ยังเชื่อมต่อไม่ได้

## สิ่งที่ต้องแก้ต่อ เรียงลำดับ

1. **แก้ function ซ้ำ:** `nexus-auth.php:8` ประกาศ `nexus_get_client_ip()` ก่อน `nexus-orders.php:27` ประกาศซ้ำโดยไม่มี guard; `nexus_check_rate_limit()` ก็ซ้ำเช่นกัน แยก helper ไว้แห่งเดียวหรือจัด guard ทั้งสองฝั่งให้ถูกต้อง แล้วตรวจ bootstrap รวม ไม่ใช่แค่ `php -l` รายไฟล์
2. **ทำให้ phpMyAdmin ใช้ config ใหม่จริง:** recreate เฉพาะ phpMyAdmin ด้วยค่า environment เดิม แล้วตรวจ Docker binding ซ้ำ อย่าอาศัย `restart` อย่างเดียวในการเปลี่ยน port publication พิจารณาปิด auto-login ด้วย ยังไม่ยืนยันว่า firewall/NAT เปิดจากอินเทอร์เน็ตหรือไม่
3. **บังคับสิทธิ์ออเดอร์ฝั่ง API:** ตรวจ token และ ownership หรือยืนยันเจ้าของเบอร์ก่อนคืนประวัติ การจำกัดย้อนหลัง 7 วัน/จำนวน 5 รายการช่วยลดข้อมูล แต่ไม่ใช่การตรวจสิทธิ์
4. **แก้ limiter ที่เชื่อ header ผู้เรียก:** ใช้ REMOTE_ADDR โดย default และยอมรับ forwarded IP เฉพาะจาก proxy ที่เชื่อถือได้ บริการนี้ publish WordPress port โดยตรง จึงต้องออกแบบให้ไม่อาศัยว่าทุกคำขอผ่าน CDN เสมอ
5. **ใช้ masking เดียวกันในทุก endpoint:** recent feed แก้แล้ว แต่ tracking ใช้ regex เดิมที่รองรับเฉพาะ 10 หลัก ขณะที่ input ยอมรับ 9 หลัก
6. **ก่อนเปิด Node service:** ปิด stub paid จนยืนยัน payment ได้จริง; อ่านราคาฝั่ง server; ตรวจเจ้าของก่อนอ่านออเดอร์

## คำสั่งตรวจซ้ำและผลที่พบ

```powershell
node security/probe-local.mjs
node security/reproduce-node.mjs
php security/reproduce-wordpress.php --retest
php security/check-wordpress-bootstrap.php
docker ps --filter name=docker-phpmyadmin-1 --format '{{.Names}} {{.Ports}}'
```

- `probe-local`: frontend 200; WordPress routes/me/track/OPTIONS 500; phpMyAdmin 200 พร้อม UI หลังล็อกอิน; webhook port 4000 ติดต่อไม่ได้
- Node reproducer: assertions ครบและ exit 0 แปลว่ายืนยันช่องโหว่เดิมได้ ไม่ใช่ว่าแอปผ่านความปลอดภัย
- PHP `--retest`: assertions ครบและ exit 0 ยืนยันทั้งกรณีที่ปิดแล้วและช่องโหว่ที่ยังอยู่ตามตาราง
- PHP bootstrap: fatal `Cannot redeclare function nexus_get_client_ip()` แม้ทดสอบโดยไม่ใช้ฐานข้อมูล
- Docker: `docker-phpmyadmin-1 0.0.0.0:8082->80/tcp, [::]:8082->80/tcp`

`--baseline` ใน PHP reproducer ยังใช้ย้อนดูโค้ดก่อนแก้ ส่วน `--retest` โหลด source ปัจจุบัน ชุดทดสอบจำลองไม่ครอบคลุม limiter concurrency/expiration, WordPress date filtering, browser CORS execution หรือ session จริงสองบัญชี

ผล dependency audit และรายละเอียดก่อนแก้อยู่ใน [รายงานรอบแรก](SECURITY-REVIEW-2026-09-10.md) ไม่รัน npm audit ซ้ำในรอบนี้ ยังไม่ได้ยืนยันความปลอดภัยของ production hosting หรือโจมตีโดเมนภายนอก
