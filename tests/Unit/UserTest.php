<?php

namespace Tests\Unit;

use App\Models\Alias;
use App\Models\AliasRecipient;
use App\Models\Recipient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser('johndoe');
    }

    #[Test]
    public function user_can_get_aliases_from_relationship()
    {
        $aliases = Alias::factory()->count(10)->create([
            'user_id' => $this->user->id,
        ]);

        $aliases->assertEquals($this->user->aliases);
    }

    #[Test]
    public function user_can_only_get_their_own_aliases_from_relationship()
    {
        $aliases = Alias::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        Alias::factory()->count(10)->create();

        $aliases->assertEquals($this->user->aliases);
    }

    #[Test]
    public function user_can_get_total_emails_forwarded()
    {
        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_forwarded' => 5,
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_forwarded' => 3,
            'deleted_at' => now(),
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_forwarded' => 2,
            'active' => false,
        ]);

        $this->assertEquals(10, $this->user->totalEmailsForwarded());
    }

    #[Test]
    public function user_can_get_total_emails_blocked()
    {
        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_blocked' => 3,
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_blocked' => 4,
            'deleted_at' => now(),
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_blocked' => 1,
            'active' => false,
        ]);

        $this->assertEquals(8, $this->user->totalEmailsBlocked());
    }

    #[Test]
    public function user_can_get_total_emails_replied()
    {
        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_replied' => 2,
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_replied' => 4,
            'deleted_at' => now(),
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'emails_replied' => 1,
            'active' => false,
        ]);

        $this->assertEquals(7, $this->user->totalEmailsReplied());
    }

    #[Test]
    public function user_can_get_aliases_using_default_recipient()
    {
        $recipient = Recipient::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $aliasNotUsingDefault = Alias::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AliasRecipient::create([
            'alias' => $aliasNotUsingDefault,
            'recipient' => $recipient,
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        Alias::factory()->create([
            'user_id' => $this->user->id,
            'active' => false,
        ]);

        $this->assertCount(2, $this->user->aliasesUsingDefault);
        $this->assertCount(3, $this->user->aliases);
    }

    #[Test]
    public function user_can_get_bandwidth_in_mb()
    {
        $this->user->update(['bandwidth' => 10485760]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'bandwidth' => 10485760,
        ]);

        $this->assertEquals(10, $this->user->bandwidth_mb);
    }

    #[Test]
    public function user_can_get_bandwidth_in_mb_to_correct_precision()
    {
        $this->user->update(['bandwidth' => 7324019]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'bandwidth' => 7324019,
        ]);

        $this->assertEquals(6.98, $this->user->bandwidth_mb);
    }

    #[Test]
    public function user_can_get_bandwidth_limit_in_mb()
    {
        $this->assertEquals(100, $this->user->getBandwidthLimitMb());
    }

    #[Test]
    public function user_can_check_if_near_bandwidth_usage_limit()
    {
        $this->user->update(['bandwidth' => 100943820]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'bandwidth' => 100943820,
        ]);

        $this->assertTrue($this->user->nearBandwidthLimit());

        $this->assertEquals(96.27, $this->user->bandwidth_mb);
    }

    #[Test]
    public function user_get_domain_options()
    {
        $username = $this->user->username;

        $domainOptions = $this->user->domainOptions();

        $expected = collect([
            'anonaddy.me',
            'anonaddy.com',
            $username.'.anonaddy.me',
            $username.'.anonaddy.com',
        ]);

        $this->assertCount($expected->count(), $domainOptions);

        $expected->zip($domainOptions)->each(function ($itemPair) {
            $this->assertEquals($itemPair[0], $itemPair[1]);
        });
    }

    #[Test]
    public function user_can_match_verified_recipient_with_extension()
    {
        $this->user->defaultRecipient->email = 'hello+anonaddy@example.com';
        $this->user->defaultRecipient->save();
        $this->assertTrue($this->user->isVerifiedRecipient('hello@example.com'));

        $this->user->defaultRecipient->email = 'hello+anonaddy+another@example.net';
        $this->user->defaultRecipient->save();
        $this->assertTrue($this->user->isVerifiedRecipient('hello@example.net'));

        $this->user->defaultRecipient->email = 'hello+anonaddy@example.net';
        $this->user->defaultRecipient->save();
        $this->assertTrue($this->user->isVerifiedRecipient('hello+anonaddy@example.net'));
    }
}
