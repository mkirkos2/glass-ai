# Glass AI

Glass AI is an AI chat application built with Laravel, Vue 3, TypeScript, and Inertia.js.

## Tech Stack

- Laravel 13
- PHP 8.3+
- Vue 3 and TypeScript
- Inertia.js
- Tailwind CSS 4
- SQLite
- Vite Plus
- Gemini API

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js
- pnpm

## Installation

1. Clone the repository:

    ```bash
    git clone https://github.com/YOUR_USERNAME/glass-ai.git
    cd glass-ai
    ```

2. Install PHP dependencies:

    ```bash
    composer install
    ```

3. Create your environment file:

    ```bash
    cp .env.example .env
    ```

    On Windows PowerShell, use:

    ```powershell
    Copy-Item .env.example .env
    ```

4. Generate the application key:

    ```bash
    php artisan key:generate
    ```

5. Create the SQLite database file if it does not exist:

    ```powershell
    New-Item -ItemType File -Path database/database.sqlite -Force
    ```

6. Configure your local environment and API credentials in `.env`. Never commit this file or publish API keys.

7. Run database migrations:

    ```bash
    php artisan migrate
    ```

8. Install frontend dependencies and build the assets:

    ```bash
    pnpm install
    pnpm build
    ```

## Development

Start the frontend development server:

```bash
pnpm dev
```

In a separate terminal, start Laravel:

```bash
php artisan serve
```

Open the local URL displayed by Laravel in your browser.

## Available Checks

```bash
pnpm check
pnpm types:check
```

Run the Laravel test suite with:

```bash
php artisan test
```

## Security

- Keep `.env` and API keys private.
- Do not commit local database files or application logs.
- Use `.env.example` to document required environment variables without including real credentials.
