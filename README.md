# AI Support Dashboard – Backend

Backend API of the **AI Support Dashboard** project, responsible for authentication, ticket CRUD, AI job processing, and analytics.

This repository is part of the **AI Support Dashboard** project.  
Project overview and architecture: https://github.com/LuisAMSantiago/ai-support-dashboard

## Tech Stack
- Laravel
- Sanctum
- Queues (database/sync)

## Requirements
- PHP >= 8.2
- Composer
- MySQL or PostgreSQL

## What this backend demonstrates
- RESTful API development with Laravel
- SPA authentication using Laravel Sanctum
- Asynchronous processing with jobs and queues
- Event modeling and activity history tracking
- Clean code organization and separation of responsibilities

## Running locally
1. `cp .env.example .env`
2. `composer install`
3. `php artisan key:generate`
4. Configure the following variables in `.env`:
    - `APP_URL=http://127.0.0.1:8000`
    - `SANCTUM_STATEFUL_DOMAINS=localhost:5173`
    - `SESSION_DOMAIN=localhost`
    - `DB_*`
5. `php artisan migrate --seed`
6. `php artisan serve`

## AI Jobs (Queues)
By default, you can use:
- `QUEUE_CONNECTION=sync` to run jobs immediately, or
- `QUEUE_CONNECTION=database` and `php artisan queue:work` for background processing.

If using `database`, make sure the queue migrations have been applied (`php artisan migrate`).

## Authentication
- SPA authentication using Laravel Sanctum
- Login available through the frontend application

## Seed data
The database seed creates a test user:
- email: `test@example.com`
- password: `password`

## AI (mock, provider-ready architecture)
The backend uses `App\Services\MockAiTicketService` by default.  
To integrate a real AI provider, create a service that implements `AiTicketServiceInterface` and update the binding in `AppServiceProvider`.