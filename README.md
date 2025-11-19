# Mini Wallet Application

A high-performance digital wallet application built with Laravel and Vue.js that allows users to transfer money to each other with real-time updates via Pusher.

## Features

- **Money Transfers**: Send money between users with automatic commission calculation (1.5%)
- **Transaction History**: View all incoming and outgoing transactions
- **Real-time Updates**: Instant balance and transaction updates via Pusher
- **High Concurrency**: Designed to handle hundreds of transfers per second with row-level locking
- **Scalable**: Balance stored in database (not calculated from millions of transactions)
- **Atomic Operations**: All transfers are wrapped in database transactions for data integrity

## Technology Stack

- **Backend**: Laravel 12
- **Frontend**: Vue.js 3 (Composition API) with Inertia.js
- **Database**: MySQL/PostgreSQL (SQLite for development)
- **Real-time**: Pusher
- **Authentication**: Laravel Fortify

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 20+ and npm
- MySQL/PostgreSQL (or SQLite for development)
- Pusher account (for real-time features)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd wallet-app
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database**

   Edit `.env` file and set your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=wallet_app
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Configure Pusher**

   Add your Pusher credentials to `.env`:
   ```env
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=your_app_id
   PUSHER_APP_KEY=your_app_key
   PUSHER_APP_SECRET=your_app_secret
   PUSHER_APP_CLUSTER=your_cluster
   ```

   Also add the Pusher key to your frontend environment (create `.env` in the root or configure in `vite.config.ts`):
   ```env
   VITE_PUSHER_APP_KEY=your_app_key
   VITE_PUSHER_APP_CLUSTER=your_cluster
   VITE_PUSHER_HOST=
   VITE_PUSHER_PORT=443
   VITE_PUSHER_SCHEME=https
   ```

7. **Run migrations**
   ```bash
   php artisan migrate
   ```

8. **Seed the database (Optional but recommended for testing)**
   
   The seeder creates 5 test users with initial balances and 8 sample transactions:
   ```bash
   php artisan db:seed
   ```
   
   **Seeded Users:**
   - Alice Johnson (alice@example.com) - Initial balance: $5,000.00
   - Bob Smith (bob@example.com) - Initial balance: $3,000.00
   - Charlie Brown (charlie@example.com) - Initial balance: $2,500.00
   - Diana Prince (diana@example.com) - Initial balance: $4,000.00
   - Eve Williams (eve@example.com) - Initial balance: $3,500.00
   
   **Default Password:** `SecurePassword123!` (for all seeded users)
   
   The seeder also creates 8 sample transactions between these users to demonstrate the transaction history feature.

9. **Build frontend assets**
   ```bash
   npm run build
   ```

## Running the Application

### Development Mode

Run the development server with hot reload:
```bash
npm run dev
```

In another terminal, start Laravel:
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

### Production Mode

Build the assets:
```bash
npm run build
```

Start the Laravel server:
```bash
php artisan serve
```

## Environment Variables

### Required Variables

```env
APP_NAME="Mini Wallet"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wallet_app
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

### Frontend Variables (Vite)

```env
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=
VITE_PUSHER_HOST=
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
```

## API Endpoints

All API endpoints require authentication via session (web middleware).

### Get Transactions

Get the authenticated user's transaction history and current balance.

**Endpoint**: `GET /api/transactions`

**Query Parameters**:
- `page` (optional): Page number for pagination (default: 1)
- `per_page` (optional): Items per page (default: 15)

**Response**:
```json
{
    "balance": "1000.00",
    "transactions": {
        "data": [
            {
                "id": 1,
                "sender_id": 1,
                "receiver_id": 2,
                "amount": "100.00",
                "commission_fee": "1.50",
                "status": "completed",
                "created_at": "2024-01-01T12:00:00.000000Z",
                "sender": {
                    "id": 1,
                    "name": "John Doe",
                    "email": "john@example.com"
                },
                "receiver": {
                    "id": 2,
                    "name": "Jane Smith",
                    "email": "jane@example.com"
                }
            }
        ],
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### Create Transfer

Execute a new money transfer.

**Endpoint**: `POST /api/transactions`

**Request Body**:
```json
{
    "receiver_id": 2,
    "amount": 100.00
}
```

**Validation Rules**:
- `receiver_id`: Required, integer, must exist in users table, cannot be the authenticated user
- `amount`: Required, numeric, minimum 0.01, must not exceed available balance (including commission)

**Response** (Success - 201):
```json
{
    "message": "Transfer completed successfully.",
    "transaction": {
        "id": 1,
        "sender_id": 1,
        "receiver_id": 2,
        "amount": "100.00",
        "commission_fee": "1.50",
        "status": "completed",
        "created_at": "2024-01-01T12:00:00.000000Z",
        "sender": {...},
        "receiver": {...}
    },
    "balance": "898.50"
}
```

**Response** (Error - 422):
```json
{
    "message": "Insufficient balance. Required: 101.50, Available: 50.00"
}
```

## Business Logic

### Commission Calculation
- Commission rate: 1.5% of the transferred amount
- Commission is charged to the sender
- Example: If User A sends 100.00 to User B:
  - User A is debited: 100.00 + 1.50 = 101.50
  - User B is credited: 100.00

### Balance Management
- User balances are stored in the `users.balance` column
- Balances are updated atomically during transfers
- Balance is NOT calculated from transaction history (for scalability)

### Concurrency Handling
- Row-level locking (`lockForUpdate()`) prevents race conditions
- Database transactions ensure atomicity
- All balance updates are wrapped in transactions

### Real-time Updates
- After a successful transfer, a `TransactionCreated` event is broadcast
- Event is sent to private channels: `user.{sender_id}` and `user.{receiver_id}`
- Frontend listens via Laravel Echo and updates UI automatically

## Testing

Run the test suite:
```bash
php artisan test
```

### Test Coverage

The test suite includes:
- Authentication requirements
- Successful transfers
- Insufficient balance scenarios
- Invalid receiver validation
- Self-transfer prevention
- Atomic transaction rollback
- Concurrent transfer handling
- Commission calculation verification
- Transaction history retrieval

## Database Schema

### Users Table
- `id`: Primary key
- `name`: User name
- `email`: Unique email address
- `password`: Hashed password
- `balance`: Decimal(15,2) - Current balance (default: 0.00)
- `created_at`, `updated_at`: Timestamps

### Transactions Table
- `id`: Primary key
- `sender_id`: Foreign key to users table
- `receiver_id`: Foreign key to users table
- `amount`: Decimal(15,2) - Transfer amount
- `commission_fee`: Decimal(15,2) - Commission charged
- `status`: String - Transaction status (default: 'completed')
- `created_at`, `updated_at`: Timestamps

**Indexes**:
- `sender_id`
- `receiver_id`
- `created_at`

## Project Structure

```
app/
├── Events/
│   └── TransactionCreated.php      # Broadcast event for real-time updates
├── Http/
│   └── Controllers/
│       └── API/
│           └── TransactionController.php
├── Models/
│   ├── Transaction.php
│   └── User.php
└── Services/
    └── TransactionService.php      # Core transfer logic with concurrency handling

database/
├── factories/
│   ├── TransactionFactory.php
│   └── UserFactory.php
├── migrations/
│   ├── XXXX_add_balance_to_users_table.php
│   └── XXXX_create_transactions_table.php
└── seeders/
    └── DatabaseSeeder.php          # Seeds 5 users with balances and transactions

resources/
└── js/
    ├── composables/
    │   ├── useEcho.ts               # Laravel Echo setup
    │   └── useTransactions.ts      # API calls composable
    └── pages/
        └── Wallet.vue               # Main wallet interface

routes/
├── api.php                          # API routes
├── channels.php                     # Broadcast channel authorization
└── web.php                          # Web routes
```

## Security Considerations

- All API endpoints require authentication
- CSRF protection enabled
- Input validation on all requests
- SQL injection prevention via Eloquent ORM
- Row-level locking prevents race conditions
- Atomic transactions ensure data integrity

## Performance Optimizations

- Database indexes on frequently queried columns
- Balance stored in users table (not calculated)
- Eager loading of relationships
- Pagination for transaction history
- Efficient query structure for large datasets

## Troubleshooting

### Pusher Connection Issues
- Verify Pusher credentials in `.env`
- Check that `BROADCAST_CONNECTION=pusher` is set
- Ensure frontend environment variables are configured
- Check browser console for connection errors

### Database Issues
- Run migrations: `php artisan migrate`
- Check database connection in `.env`
- Verify database exists and user has permissions
- Reset database with fresh seed data: `php artisan migrate:fresh --seed`

### Frontend Build Issues
- Clear cache: `npm run build`
- Delete `node_modules` and reinstall: `rm -rf node_modules && npm install`

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Author

Developed as a technical assignment demonstrating full-stack Laravel and Vue.js skills with focus on high-performance, scalable financial systems.

