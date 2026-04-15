# 🚀 Ecommerce API - Managed with Laravel 12

A powerful, real-time, and secure Ecommerce API built with **Laravel 12**. This project features a multi-gateway payment system, real-time notifications via **Laravel Reverb**, automated inventory management, and a robust role-based access control system.

---

## 🌟 Key Features | المميزات الأساسية

### 🛡️ Authentication & Authorization
- **Sanctum Auth**: Secure token-based authentication.
- **RBAC**: Role-Based Access Control using `Spatie Laravel Permission` (Admin, Customer).

### 🛒 Core Commerce
- **Product Management**: Full CRUD with search, category filtering, and soft-delete support.
- **Category System**: Hierarchical category management.
- **Cart System**: Real-time shopping cart management.
- **Checkout**: Seamless checkout experience with validation and inventory safety.
- **Order Cancellation**: Self-service order cancellation for specific payment methods (COD) within allowed windows.

### 💳 Payments | نظام الدفع (Multi-Gateway)
- **Paymob Integration**: Support for Credit Cards & Fawry (local Egypt payments) with robust Webhook handling.
- **Stripe Integration**: Global secure card payments.
- **Cash on Delivery (COD)**: Specialized flow for cash orders with manual review status.
- **Refund Policy System**: Backend support for automated refunds via payment gateways.

### 📦 Inventory Management | إدارة المخزون
- **Automated Stock Control**: Stock is automatically deducted upon successful payment (or order creation for COD) and restored upon cancellation.

### ⚡ Real-time & Monitoring
- **Real-time Notifications**: Instant updates on order status using **Laravel Reverb** (WebSockets).
- **Admin Stats**: Dashboard-ready statistics for sales, orders, and product performance.

---

## 🛠️ Tech Stack | التقنيات المستخدمة

- **Framework**: [Laravel 12](https://laravel.com)
- **Real-time**: [Laravel Reverb](https://laravel.com/docs/reverb)
- **Auth**: [Laravel Sanctum](https://laravel.com/docs/sanctum)
- **Permissions**: [Spatie Permission](https://spatie.be/docs/laravel-permission)
- **Payments**: Paymob & Stripe SDKs
- **Monitoring**: Laravel Telescope (Optional)


---

## 🚀 Installation | التثبيت

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL/PostgreSQL

### Setup Steps
1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/ecommerce-api.git
   cd ecommerce-api
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Configuration:**
   Update your `.env` file with your database credentials.

5. **Run Migrations & Seeders:**
   ```bash
   php artisan migrate --seed
   ```

6. **Start WebSockets (Reverb):**
   ```bash
   php artisan reverb:start
   ```

7. **Start the Development Server:**
   ```bash
   npm run dev
   ```
   *Note: This runs the server, queue, and vite concurrently.*

---

## 📖 API Documentation

The API endpoints are organized under the `api/` prefix. Key routes include:
- `POST /api/register` & `POST /api/login`
- `GET /api/products` (Searchable)
- `POST /api/checkout`
- `POST /api/orders/{order}/cancel` (Customer self-cancel)
- `PATCH /api/orders/{order}/status` (Admin only)
## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

