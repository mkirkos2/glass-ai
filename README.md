# Glass AI

**An AI-powered chat application built with Laravel and Vue.js.**

Glass AI is a full-stack web application that provides an interactive AI chat experience powered by the Gemini API. It combines a Vue 3 and TypeScript frontend with a Laravel backend to manage conversations, messages, authentication, and user settings.

The project focuses on a clean user experience, persistent conversation history, and a maintainable application structure.

## Features

- **AI-powered conversations** — Send messages and receive AI-generated responses through the Gemini API.
- **Conversation management** — Create new conversations, revisit previous chats, and delete conversations with confirmation.
- **Persistent chat history** — Store conversations and messages in a relational database.
- **User authentication** — Registration, login, email verification, and password reset.
- **Account management** — Update profile information and manage security settings.
- **Appearance settings** — Customize the application's appearance.
- **Responsive interface** — A modern interface built with Vue 3, Tailwind CSS, and reusable UI components.
- **Error and loading states** — Provide feedback during asynchronous operations and when requests fail.

## Tech Stack

| Technology      | Purpose                                 |
| --------------- | --------------------------------------- |
| PHP 8.3+        | Backend language                        |
| Laravel 13      | Backend framework and application logic |
| Vue 3           | Reactive user interface                 |
| TypeScript      | Static typing for frontend code         |
| Inertia.js      | Integration between Laravel and Vue     |
| Tailwind CSS 4  | Utility-first styling                   |
| SQLite          | Relational database                     |
| Gemini API      | AI-generated responses                  |
| Vite Plus       | Frontend development and build tooling  |
| Laravel Fortify | Authentication functionality            |

## Requirements

Make sure the following tools are installed:

- PHP 8.3 or newer
- Composer
- Node.js
- npm
- A Gemini API key

## Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/mkirkos2/glass-ai.git
cd glass-ai
```

### 2. Install backend dependencies

```bash
composer install
```

### 3. Configure environment variables

Create a local environment file:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate the application encryption key:

```bash
php artisan key:generate
```

Configure your local database and Gemini API credentials in `.env`. Keep this file private and never commit real credentials to version control.

### 4. Set up the database

Glass AI uses SQLite by default. Create the database file if it does not already exist.

On Windows PowerShell:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

Run the database migrations:

```bash
php artisan migrate
```

### 5. Install frontend dependencies

```bash
npm ci
```

Build the frontend assets:

```bash
npm run build
```

### 6. Start the application

Start the Laravel development server:

```bash
php artisan serve
```

In a separate terminal, start the frontend development server:

```bash
npm run dev
```

Open the local URL provided by Laravel in your browser.

## Development and Quality Checks

Run the frontend formatter and linter checks:

```bash
npm run check
```

Run the TypeScript type checker:

```bash
npm run types:check
```

Run the Laravel automated test suite:

```bash
php artisan test
```

## Project Structure

```text
app/
  Http/Controllers/     Application controllers
  Models/               Eloquent models
  Actions/               Authentication actions
  Providers/             Application service providers

database/
  migrations/            Database schema migrations
  factories/             Model factories
  seeders/               Database seeders

resources/
  js/
    components/          Reusable Vue components
    layouts/             Application layouts
    pages/                Inertia pages
    composables/          Reusable Vue composables
    lib/                  Frontend utilities
  css/                    Application styles
  views/                  Blade templates

routes/                   Application routes
tests/                    Automated tests
```

## Security

- Keep `.env` files and API credentials private.
- Never commit API keys, passwords, or other sensitive configuration.
- Keep local database files and application logs out of version control.
- Use environment variables for environment-specific configuration.
- Validate user input and enforce appropriate authorization on application operations.

## License

No license has been specified yet. All rights are reserved unless a license is added to this repository.
