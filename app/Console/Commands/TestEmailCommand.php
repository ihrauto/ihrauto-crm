<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mail:test {email : The email address to send test email to}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test email to verify email configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $this->info("Sending test email to: {$email}");
        $this->info("Using mailer: " . config('mail.default'));
        $this->info("From address: " . config('mail.from.address'));

        try {
            Mail::raw('This is a test email from IHRAUTO CRM. If you received this, your email configuration is working correctly!', function ($message) use ($email) {
                $message->to($email)
                    ->subject('IHRAUTO CRM - Test Email');
            });

            $this->info('✅ Test email sent successfully!');
            $this->info("Check your inbox at: {$email}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test email:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
