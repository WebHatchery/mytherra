# Mytherra PHP Backend

This is the PHP version of the Mytherra game backend, converted from the original Node.js/TypeScript implementation.

## Architecture Overview

The Mytherra PHP backend follows the same layered architecture pattern as the Node.js version:

```
routes → controllers → actions → models → database
```

- **Routes**: Define API endpoints and map them to controller methods
- **Controllers**: Handle HTTP requests/responses and delegate business logic to actions
- **Actions**: Contain all business logic and data manipulation
- **Models**: Define database schemas and relationships using Eloquent ORM
- **Utils**: Shared utilities and helper functions

## Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer

## Installation

1. Install dependencies:
```bash
composer install
```

2. Copy environment configuration:
```bash
copy .env.example .env
```

3. Update the `.env` file with your database credentials:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mytherra
DB_USER=root
DB_PASSWORD=
```

4. Seed the database:
```bash
php scripts/seedDb.php
```

5. Start the development server:
```bash
composer start
```

The server will run on `http://localhost:5002` by default.

## API Endpoints

### Regions
- `GET /api/regions` - Get all regions
- `GET /api/regions/{id}` - Get region by ID
- `POST /api/regions/{id}/process` - Process region tick
- `POST /api/regions` - Create new region

### Heroes
- `GET /api/heroes` - Get all heroes
- `GET /api/heroes/{id}` - Get hero by ID

### Events
- `GET /api/events` - Get all events (with pagination)
- `GET /api/events/{id}` - Get event by ID

### Influence Actions
- `POST /api/influence/region/{id}` - Apply influence action to region
- `POST /api/influence/hero/{id}` - Apply influence action to hero

### Game Status
- `GET /api/status` - Get current game status
- `GET /api/site-status` - Get API operational status

## Development Guidelines

### Models

Models use Laravel's Eloquent ORM and follow the same schema as the Node.js version:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['id', 'name', 'color', 'prosperity', 'chaos', 'magic_affinity'];
    protected $casts = ['event_ids' => 'array'];
    protected $keyType = 'string';
    public $incrementing = false;
}
```

### Controllers

Controllers handle HTTP-specific concerns and delegate to action classes:

```php
<?php
namespace App\Controllers;

class RegionController
{
    public function getAllRegions(Request $request, Response $response): Response
    {
        $regions = RegionActions::fetchAllRegions();
        $response->getBody()->write(json_encode($regions->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

### Actions

Actions contain business logic and are static classes for simplicity:

```php
<?php
namespace App\Actions;

class RegionActions
{
    public static function fetchAllRegions()
    {
        return Region::all();
    }
}
```

## Key Differences from Node.js Version

2. **ORM**: Uses Eloquent ORM instead of Sequelize
3. **Database**: Still uses MySQL with the same schema
4. **Architecture**: Maintains the same layered architecture pattern
5. **API Compatibility**: All endpoints return the same JSON responses

## Database Schema

The database schema is identical to the Node.js version:

- `regions` - World regions with prosperity, chaos, and magic affinity
- `heroes` - Game heroes with roles, power levels, and attributes
- `game_events` - World events with descriptions and related entities
- `game_states` - Singleton table for current game year
- `players` - Singleton table for player divine favor
- `game_configs` - Configuration values (extensible)

## Environment Variables

- `DB_HOST` - Database host
- `DB_PORT` - Database port
- `DB_CONNECTION` - Database driver
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password
- `CORS_ALLOWED_ORIGINS` - Comma-separated list of allowed browser origins
- `JWT_SECRET` - Shared WebHatchery JWT signing secret
- `WEB_HATCHERY_LOGIN_URL` - Shared WebHatchery login URL returned with 401 responses
- `WEB_HATCHERY_REGISTER_URL` - Shared WebHatchery registration URL used by account-link UI
- `PORT` - Server port
- `DEBUG` - Enable debug mode

## Testing

The API endpoints are compatible with the existing Bruno test suite in the Node.js backend. Simply update the base URL to `http://localhost:5002/api` to test the PHP backend.

## Deployment

For production deployment:

1. Set `DEBUG=false` in `.env`
2. Configure proper web server (Apache/Nginx)
3. Set up SSL certificates
4. Configure database connection pooling
5. Set up proper logging and monitoring

## Game Loop

The game loop functionality from the Node.js version is not yet implemented in this PHP version. This would require:

1. Background job processing (using something like Laravel Queue or ReactPHP)
2. Scheduled tasks for periodic game state updates
3. WebSocket support for real-time updates (optional)

This PHP backend currently provides a REST API compatible with the existing frontend.
