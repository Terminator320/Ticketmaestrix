# TicketMaestrix

## Description
TicketMaestrix will be a web application that will allow users to purchase tickets for various events such as concerts, raffles, movies, etc. 
This platform shall target event attendees and event organizers. Users can create accounts, browse events and manage their purchases, while admins can create and manage events, 
control ticket availability, and monitor transactions. Our system prioritizes ease of access, secure transactions and scalability, ensuring an easy and reliable experience for both 
admins and clients.

## Dependencies

### Templates
https://github.com/frostybee/slim-mvc

## Authors

George Vogas (2480396)
Fadwa Shalby (6296112)
Lucas Coveyduck (2478812)

## License

This project is licensed under the MIT License - see the LICENSE.txt file for details

## Deployment

### Prerequisites
- cPanel hosting with SSH access
- Composer installed on server
- PHP 8.4+ with required extensions (pdo_mysql, mbstring)

### SSH Key Setup
1. Generate SSH key pair locally: `ssh-keygen -t ed25519 -C "deploy@ticketmaestrix"`
2. Add public key to cPanel: cPanel → SSH Access → Manage SSH Keys → Import Key
3. Authorize the key in cPanel

### Configuration
1. Copy `.env.example` to `.env` on the server
2. Update `.env` with production database credentials
3. Set `APP_DEBUG=false` for production

### Auto-Deploy via cPanel Git
1. In cPanel: Git Version Control → Create → Clone Repository
2. Set repository URL and deployment path
3. The `.cpanel.yml` will run deployment tasks automatically

### Manual Deploy
```bash
# Update deployment credentials in .cpanel.yml first
ssh user@your-server.com "cd ~/public_html && git pull && composer install --no-dev --optimize-autoloader"
```

## Acknowledgments

Inspiration, code snippets, etc.
* [DomPizzie/README-Template](https://gist.github.com/DomPizzie/7a5ff55ffa9081f2de27c315f5018afc)
