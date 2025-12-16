# TicketNinja PHP Backend API

Modern, scalable PHP backend for the TicketNinja event management platform.

## 🚀 Features

- **RESTful API** with clean architecture
- **JWT Authentication** for secure access
- **MySQL Database** with phpMyAdmin support
- **MVC Pattern** for maintainability
- **Middleware Support** for request processing
- **Input Validation** for data integrity
- **CORS Handling** for frontend integration
- **Repository Pattern** for database operations
- **AI-Powered Analytics** for insights
- **Scalable Architecture** for future growth

## 📋 Requirements

- PHP 7.4 or higher (8.0+ recommended)
- MySQL 5.7 or higher
- Composer (PHP package manager)
- Apache/Nginx web server with mod_rewrite
- phpMyAdmin (optional, for database management)

## 🛠️ Installation

### 1. Install Composer Dependencies

```bash
cd backend-php
composer install
```

### 2. Configure Environment

Copy `.env.example` to `.env` and update the database credentials:

```bash
copy .env.example .env
```

Edit `.env`:
```env
DB_HOST=localhost
DB_DATABASE=ticketninja
DB_USERNAME=root
DB_PASSWORD=your_password_here
JWT_SECRET=your-super-secret-key-change-in-production
```

### 3. Create Database

Using phpMyAdmin:
1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Click "New" to create a database
3. Name it `ticketninja`
4. Set collation to `utf8mb4_unicode_ci`
5. Click "Create"

Alternatively, import the provided SQL file:
1. In phpMyAdmin, select the `ticketninja` database
2. Click "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"

### 4. Configure Web Server

#### For Apache (XAMPP/WAMP)

1. Place the `backend-php` folder in your web root (e.g., `htdocs` or `www`)
2. Access via: `http://localhost/backend-php/`

#### For Production

Create a virtual host pointing to the `backend-php` directory.

## 📂 Project Structure

```
backend-php/
├── src/
│   ├── Core/              # Core application classes
│   │   ├── Application.php    # Main application & routing
│   │   ├── Database.php       # Database singleton
│   │   ├── Response.php       # Response formatter
│   │   └── Router.php         # HTTP router
│   ├── Controllers/       # Request handlers
│   │   ├── AuthController.php
│   │   ├── EventController.php
│   │   ├── AttendeeController.php
│   │   ├── AnalyticsController.php
│   │   ├── UserController.php
│   │   └── WithdrawalController.php
│   ├── Models/            # Database models
│   │   ├── BaseModel.php
│   │   ├── User.php
│   │   ├── Event.php
│   │   ├── Attendee.php
│   │   └── Withdrawal.php
│   ├── Middleware/        # Request middleware
│   │   └── AuthMiddleware.php
│   └── Utils/             # Utility classes
│       └── Validator.php
├── database/             # Database files
│   └── schema.sql       # MySQL schema
├── vendor/              # Composer dependencies
├── .env                 # Environment config (create from .env.example)
├── .env.example         # Environment template
├── .htaccess           # Apache rewrite rules
├── index.php           # Application entry point
├── composer.json       # PHP dependencies
└── README.md          # This file
```

## 🔌 API Endpoints

### Authentication (Public)
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - User login
- `POST /api/auth/forgot-password` - Password recovery

### Users (Protected)
- `GET /api/user/profile` - Get user profile
- `PUT /api/user/profile` - Update user profile

### Events
- `GET /api/events` - List all events (public)
- `GET /api/events/{id}` - Get event details (public)
- `POST /api/events` - Create event (protected)
- `PUT /api/events/{id}` - Update event (protected)
- `DELETE /api/events/{id}` - Delete event (protected)
- `GET /api/events/user/{userId}` - Get user events (protected)

### Attendees (Protected)
- `GET /api/attendees` - List attendees
- `GET /api/attendees/{id}` - Get attendee details
- `POST /api/attendees` - Register attendee
- `PUT /api/attendees/{id}` - Update attendee
- `DELETE /api/attendees/{id}` - Delete attendee
- `GET /api/attendees/event/{eventId}` - Get event attendees

### Analytics (Protected)
- `GET /api/analytics/overview` - Get analytics overview
- `GET /api/analytics/events/{eventId}` - Get event analytics
- `GET /api/analytics/ai-insights` - Get AI-powered insights

### Withdrawals (Protected)
- `GET /api/withdrawals` - List withdrawals
- `GET /api/withdrawals/{id}` - Get withdrawal details
- `POST /api/withdrawals` - Create withdrawal request
- `PUT /api/withdrawals/{id}/status` - Update withdrawal status

## 🔐 Authentication

Protected routes require a JWT token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

The token is returned after successful login or registration.

## 📊 Response Format

All API responses follow this standard format:

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

## 🚀 Deployment

### Production Checklist

1. **Environment**: Set `APP_ENV=production` and `APP_DEBUG=false`
2. **Security**: Generate a strong `JWT_SECRET`
3. **Database**: Use secure database credentials
4. **HTTPS**: Enable SSL certificate
5. **File Permissions**: Secure file/directory permissions
6. **Error Logging**: Configure proper error logging
7. **Backup**: Set up database backup strategy

## 🧪 Testing

```bash
composer test
```

## 📝 License

MIT License

## 👨‍💻 Development

### Adding New Routes

Edit `src/Core/Application.php` and add routes in the `registerApiRoutes()` method.

### Creating New Controllers

1. Create a new file in `src/Controllers/`
2. Extend from base controller behavior
3. Return responses using `Response::success()` or `Response::error()`

### Adding Validation Rules

Edit `src/Utils/Validator.php` to add custom validation rules.

## 🤝 Support

For issues or questions, please contact the development team.

---

**TicketNinja** - AI-Powered Event Management Platform
