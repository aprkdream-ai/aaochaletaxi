<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverService
{
    public function __construct(private PlanLimitService $limits, private AuditLogger $audit) {}

    public function create(Tenant $tenant, array $data, ?string $appPassword): Driver
    {
        $this->limits->assertCanAdd($tenant, 'drivers');

        return DB::transaction(function () use ($data, $appPassword) {
            $driver = Driver::create($data);
            if ($appPassword) {
                $this->enableAppAccess($driver, $appPassword);
            }
            $this->audit->log('driver.created', $driver, [], ['name' => $driver->name, 'app_access' => (bool) $appPassword]);

            return $driver;
        });
    }

    public function update(Driver $driver, array $data): Driver
    {
        return DB::transaction(function () use ($driver, $data) {
            $old = $driver->only(array_keys($data));
            $driver->update($data);

            if ($user = $driver->user) {
                $this->assertPhoneFree($driver->phone, $user->id);
                $user->forceFill([
                    'name' => $driver->name, 'phone' => $driver->phone,
                    'status' => $driver->status === DriverStatus::Inactive ? UserStatus::Inactive : UserStatus::Active,
                ])->save();
            }
            $this->audit->log('driver.updated', $driver, $old, $driver->only(array_keys($data)));

            return $driver;
        });
    }

    /** Creates (or re-activates) the driver's login for the Driver app (login with mobile + password). */
    public function enableAppAccess(Driver $driver, string $password): User
    {
        $user = $driver->user;
        if (! $user) {
            $this->assertPhoneFree($driver->phone);
            $user = new User(['name' => $driver->name, 'phone' => $driver->phone, 'email' => null, 'password' => $password]);
            $user->type = UserType::Driver;
            $user->tenant_id = $driver->tenant_id;
            $user->branch_id = $driver->branch_id;
        } else {
            $user->password = $password;
        }
        $user->status = UserStatus::Active;
        $user->save();

        $driver->user_id = $user->id;
        $driver->save();
        $this->audit->log('driver.app_access_enabled', $driver);

        return $user;
    }

    public function disableAppAccess(Driver $driver): void
    {
        if ($driver->user) {
            $driver->user->forceFill(['status' => UserStatus::Inactive])->save();
            $this->audit->log('driver.app_access_disabled', $driver);
        }
    }

    public function delete(Driver $driver): void
    {
        DB::transaction(function () use ($driver) {
            $this->disableAppAccess($driver);
            $driver->delete();
            $this->audit->log('driver.deleted', $driver, ['name' => $driver->name]);
        });
    }

    private function assertPhoneFree(string $phone, ?int $ignoreUserId = null): void
    {
        $taken = User::withTrashed()->where('phone', $phone)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['phone' => __('This mobile number already has an AaoChaleTaxi login. Use a different number for driver app access.')]);
        }
    }
}
