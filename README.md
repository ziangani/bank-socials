# Social Banking System

A modular social banking system built with Laravel 11 that handles both WhatsApp and USSD channels.

[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2F35849a4b-6a83-4236-ae62-8ca0b9da65ec&style=plastic)](https://forge.laravel.com/servers/820094/sites/2525696)

## Features

- Multi-channel support (WhatsApp & USSD)
- Session management
- Transaction processing
- Response formatting
- State management
- Security & authentication
- USSD simulator interface

### Core Modules

- **Authentication & Registration**
  - Card-based registration
  - Account-based registration
  - OTP handling
  - PIN management
  - Session validation

- **Money Transfer**
  - Internal transfers
  - Bank-to-bank transfers
  - Mobile money transfers
  - Transaction validation
  - Amount limits enforcement

- **Bill Payments**
  - Service provider integration
  - Reference number validation
  - Fixed/flexible amount handling
  - Payment confirmation

- **Account Services**
  - Balance inquiry
  - Mini statement
  - Full statement
  - Account validation

- **System Administration**
  - Transaction monitoring
  - User management
  - System configuration
  - Report generation

## Requirements

- PHP 8.2+
- Laravel 11
- PgSQL 15.0+
- Redis (optional, for session management)
- Composer

## Installation

1. Clone the repository:
```bash
git clone https://github.com/ziangani/social-banking.git
cd social-banking
```

2. Install dependencies:
```bash
composer install
```

3. Copy environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Configure your database in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=social_banking
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

6. Run migrations:
```bash
php artisan migrate
```

7. Configure WhatsApp and USSD settings in `.env`:
```
WHATSAPP_ENABLED=true
WHATSAPP_WEBHOOK_URL=your_webhook_url
WHATSAPP_VERIFY_TOKEN=your_verify_token
WHATSAPP_ACCESS_TOKEN=your_access_token

USSD_ENABLED=true
USSD_SERVICE_CODE=*123#
```

## Usage

### Starting the Application

1. Start the development server:
```bash
php artisan serve
```

2. Start the queue worker (if using queues):
```bash
php artisan queue:work
```

### API Endpoints

#### WhatsApp Endpoints
```
POST /whatsapp/webhook - Handle WhatsApp messages
GET /whatsapp/verify - Verify WhatsApp webhook
```

#### USSD Endpoints
```
POST /ussd/handle - Handle USSD requests
POST /ussd/end-session - End USSD session
GET /ussd/session-status - Check session status
```

#### Transaction Endpoints
```
GET /api/transactions - List transactions
POST /api/transactions - Create transaction
GET /api/transactions/{reference} - Get transaction details
GET /api/transactions/{reference}/status - Check transaction status
POST /api/transactions/{reference}/reverse - Reverse transaction
```

## Testing

Run the test suite:
```bash
php artisan test
```

### USSD Simulator

Access the USSD simulator at:
```
GET /ussd/simulator
```

## Configuration

The system can be configured through various configuration files:

- `config/social-banking.php` - Main configuration
- `config/whatsapp.php` - WhatsApp specific settings
- `.env` - Environment specific settings

## Security

The system implements several security measures:

- PIN encryption
- Session security
- Input validation
- Transaction limits
- Fraud prevention
- Rate limiting

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please email support@example.com or open an issue in the GitHub repository.
