<?php

namespace App\Actions\Fortify;

use App\Models\Settings;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

// BR-1.1 to BR-1.4: customer registration.
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => Phone::rules(),
            'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.today()->subYears(18)->toDateString()],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Please enter a valid mobile number.',
            'date_of_birth.before_or_equal' => 'The account holder must be 18 or older.',
            'terms.accepted' => 'Please accept the Terms and Privacy Policy.',
        ])->validate();

        $user = new User([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'date_of_birth' => $input['date_of_birth'],
            'password' => $input['password'],
            'marketing_opt_in' => (bool) ($input['marketing_opt_in'] ?? false),
        ]);
        $user->terms_version = Settings::current()->terms_version;
        $user->terms_accepted_at = now();
        $user->save();

        return $user;
    }
}
