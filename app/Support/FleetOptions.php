<?php

namespace App\Support;

/** Option lists for fleet forms (values are stored; labels translated). */
class FleetOptions
{
    public static function fuelTypes(): array
    {
        return ['diesel' => __('Diesel'), 'petrol' => __('Petrol'), 'cng' => 'CNG', 'electric' => __('Electric'), 'hybrid' => __('Hybrid')];
    }

    public static function ownership(): array
    {
        return ['owned' => __('Owned'), 'attached' => __('Attached (owner-driver)'), 'vendor' => __('Vendor / partner')];
    }

    public static function vehicleDocuments(): array
    {
        return ['rc' => __('Registration certificate (RC)'), 'insurance' => __('Insurance'), 'permit' => __('Permit'), 'fitness' => __('Fitness certificate'), 'puc' => 'PUC', 'other' => __('Other')];
    }

    public static function driverDocuments(): array
    {
        return ['licence' => __('Driving licence'), 'aadhaar' => 'Aadhaar', 'pan' => 'PAN', 'police_verification' => __('Police verification'), 'badge' => __('Driver badge'), 'other' => __('Other')];
    }

    public static function maintenanceTypes(): array
    {
        return ['service' => __('Periodic service'), 'repair' => __('Repair'), 'tyres' => __('Tyres'), 'battery' => __('Battery'), 'accident' => __('Accident'), 'other' => __('Other')];
    }

    public static function expenseCategories(): array
    {
        return ['fuel' => __('Fuel'), 'toll' => __('Toll'), 'parking' => __('Parking'), 'fine' => __('Fine / challan'), 'cleaning' => __('Cleaning'), 'emi' => __('Loan EMI'), 'insurance' => __('Insurance'), 'permit' => __('Permit / tax'), 'other' => __('Other')];
    }

    public static function payTypes(): array
    {
        return ['monthly' => __('Monthly salary'), 'per_trip' => __('Per trip'), 'commission' => __('Commission %')];
    }

    public static function licenceTypes(): array
    {
        return ['LMV' => 'LMV', 'LMV-TR' => 'LMV-TR (Transport)', 'MCWG/LMV' => 'MCWG + LMV', 'HMV' => 'HMV', 'HPMV' => 'HPMV / HTV', 'TRANS' => __('Transport (badge)')];
    }

    public static function vehicleExpiryFields(): array
    {
        return ['insurance_expiry' => __('Insurance'), 'permit_expiry' => __('Permit'), 'fitness_expiry' => __('Fitness'), 'puc_expiry' => 'PUC'];
    }
}
