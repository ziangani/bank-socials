<?php

namespace App\Console\Commands;

use App\Integrations\ESB;
use Illuminate\Console\Command;

class testEsb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-esb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ESB integration services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $esb = new ESB();
        $testAccounts = [
            '1850002685954',
            '1260000000235',
            // '1630000056933',
            // '1010100007998',
            // '1010100010894',
            '9999999999999' // Invalid account for error testing
        ];

        $this->info('Testing Account Details Service:');
        $this->line('Including an invalid account to demonstrate error handling');
        $this->newLine();
        
        foreach ($testAccounts as $account) {
            $this->info("Getting details for account: $account");
            $result = $esb->getAccountDetailsAndBalance($account);
            
            if ($result['status']) {
                $this->info('✓ Success:');
                $this->table(
                    ['Field', 'Value'],
                    collect($result['data'])->map(fn($value, $key) => [$key, $value ?: 'N/A'])->toArray()
                );
            } else {
                $this->error("✗ Failed: " . (is_array($result['message']) ? json_encode($result['message']) : $result['message']));
            }
            $this->newLine();
        }

        $this->info('Testing OTP Service:');
        $this->newLine();
        
        $mobileNumber = '260964926646';
        $this->info("Generating OTP for mobile number: $mobileNumber");
        
        $result = $esb->generateOTP($mobileNumber);
        
        if ($result['status']) {
            $this->info('✓ OTP generated successfully:');
            $this->table(
                ['Field', 'Value'],
                collect($result['data'])->map(fn($value, $key) => [$key, $value ?: 'N/A'])->toArray()
            );
        } else {
            $this->error("✗ OTP generation failed: " . (is_array($result['message']) ? json_encode($result['message']) : $result['message']));
        }
        
        $this->newLine();
        $this->info('Testing Transfer Service:');
        $this->newLine();
        
        // Test transfer between first two accounts
        $fromAccount = $testAccounts[0];
        $toAccount = $testAccounts[1];
        $amount = '1';
        
        $this->info("Attempting transfer:");
        $this->line("From: $fromAccount");
        $this->line("To: $toAccount");
        $this->line("Amount: MWK $amount");
        
        $result = $esb->transferToBankAccount($fromAccount, $toAccount, $amount);
        
        if ($result['status']) {
            $this->info('✓ Transfer successful');
            if (!empty($result['data'])) {
                $this->table(
                    ['Field', 'Value'],
                    collect($result['data'])->map(fn($value, $key) => [$key, $value ?: 'N/A'])->toArray()
                );
            }
        } else {
            $this->error("✗ Transfer failed: " . (is_array($result['message']) ? json_encode($result['message']) : $result['message']));
        }
    }
}
