<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WebScheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebSchedulerTest extends TestCase
{
    public function test_tick_drains_queue_once_per_minute_and_stops_when_cron_is_active(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'array', 'aaochale.web_scheduler' => true]);
        WebSchedulerProbeJob::dispatch();
        $this->assertSame(1, \DB::table('jobs')->count());

        $this->assertTrue(WebScheduler::tick());
        $this->assertSame(0, \DB::table('jobs')->count());
        $this->assertTrue(Cache::get('probe-ran'));
        $this->assertSame('web', WebScheduler::lastRun()['via']);

        $this->assertFalse(WebScheduler::tick(), 'rate limited to once a minute');

        Cache::forget('scheduler:web_tick');
        WebScheduler::cronHeartbeat();
        $this->assertTrue(WebScheduler::cronIsActive());
        $this->assertFalse(WebScheduler::tick(), 'disengages when cron runs');
        $this->assertSame('cron', WebScheduler::lastRun()['via']);
    }

    public function test_admin_dashboard_warns_without_cron(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Cron job not detected');
        WebScheduler::cronHeartbeat();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('Cron job not detected');
    }

    public function test_support_phone_link_is_e164(): void
    {
        config(['aaochale.support_phone' => '+91 90094 34118', 'aaochale.support_phone_e164' => '+919009434118']);
        $this->get(route('contact'))->assertSee('href="tel:+919009434118"', false)->assertSee('+91 90094 34118');
    }
}

class WebSchedulerProbeJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        Cache::put('probe-ran', true);
    }
}
