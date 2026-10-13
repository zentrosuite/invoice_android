<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The database connection details.
 */
final class DatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The installer's own guard is the marker file, enforced by RedirectIfNotInstalled.
        // There is no user to authorize against — that is what this flow creates.
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
            'create_database' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'database.regex' => 'A database name can only contain letters, numbers and '
                . 'underscores. Anything else cannot be used safely in a CREATE statement.',
            'database.max' => 'MySQL will not accept a database name longer than 64 characters.',
            'host.required' => 'Enter the database host — 127.0.0.1 if MySQL is on this machine.',
            'port.integer' => 'The port has to be a number. MySQL uses 3306 unless it was changed.',
        ];
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public function config(): array
    {
        $validated = $this->validated();

        return [
            'host' => trim((string) $validated['host']),
            'port' => (int) $validated['port'],
            'database' => trim((string) $validated['database']),
            'username' => trim((string) $validated['username']),
            'password' => (string) ($validated['password'] ?? ''),
        ];
    }
}
