# College Project Management System

A comprehensive web-based project management system for colleges that allows both students and faculty members to manage academic projects effectively.

## Features

### For Students
- User registration and login
- Project proposal submission
- Document upload (synopsis, report, presentation, source code)
- Project status tracking
- View faculty feedback and evaluations
- Download certificates upon project completion

### For Faculty (Admins)
- Secure login
- Review and manage student projects
- Evaluate project submissions
- Provide feedback and comments
- Set project deadlines
- Monitor overall progress
- Generate reports

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/college-pmt.git
   ```

2. Create a MySQL database named `pmt_db`

3. Import the database schema:
   ```bash
   mysql -u your_username -p pmt_db < database.sql
   ```

4. Configure the database connection:
   - Open `config/database.php`
   - Update the database credentials:
     ```php
     define('DB_SERVER', 'localhost');
     define('DB_USERNAME', 'your_username');
     define('DB_PASSWORD', 'your_password');
     define('DB_NAME', 'pmt_db');
     ```

5. Set up the upload directory:
   ```bash
   mkdir uploads
   chmod 777 uploads
   ```

6. Configure your web server to point to the project directory

## Directory Structure

```
college-pmt/
├── admin/              # Admin-specific files
├── student/            # Student-specific files
├── config/             # Configuration files
├── uploads/            # Uploaded files
├── assets/            # Static assets (CSS, JS, images)
├── index.php          # Landing page
├── login.php          # Login page
├── register.php       # Registration page
└── logout.php         # Logout script
```

## Usage

1. Access the system through your web browser
2. Register as either a student or faculty member
3. Log in with your credentials
4. Follow the on-screen instructions to manage projects

## Security Features

- Password hashing
- SQL injection prevention
- XSS protection
- CSRF protection
- Input validation
- Secure file upload handling

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please email support@college-pmt.com or create an issue in the repository.

## Acknowledgments

- Bootstrap for the frontend framework
- Font Awesome for icons
- All contributors who have helped improve the system 