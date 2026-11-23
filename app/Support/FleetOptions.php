<?php

namespace App\Support;

/** Option lists for fleet forms (values are stored; labels translated). */
class FleetOptions
{
    public static function fuelTypes(): array
    {
        return ['diesel' => __('Diesel'), 'petrol' => __('Petrol'), 'cng' => 'CNG', 'electric' => __('Electric'), 'hybrid' => __('Hybrid')];
    }

    /** Body type, independent of the (fare) category. */
    public static function vehicleTypes(): array
    {
        return ['car' => __('Car'), 'suv' => 'SUV / MUV', 'van' => __('Van'), 'tempo' => __('Tempo traveller'), 'bus' => __('Bus'),
            'pickup' => __('Pickup / light truck'), 'auto' => __('Auto rickshaw'), 'bike' => __('Two-wheeler'), 'other' => __('Other')];
    }

    public static function ownership(): array
    {
        return ['owned' => __('Owned'), 'attached' => __('Attached (owner-driver)'), 'vendor' => __('Vendor / partner')];
    }

    public static function vehicleDocuments(): array
    {
        return ['rc' => __('Registration certificate (RC)'), 'insurance' => __('Insurance'), 'permit' => __('Permit'), 'fitness' => __('Fitness certificate'), 'puc' => 'PUC', 'road_tax' => __('Road tax'), 'other' => __('Other')];
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

    /** Required documents: an empty date counts as "missing". */
    public static function vehicleExpiryFields(): array
    {
        return ['insurance_expiry' => __('Insurance'), 'permit_expiry' => __('Permit'), 'fitness_expiry' => __('Fitness'), 'puc_expiry' => 'PUC'];
    }

    /** Optional documents: checked only when a date is set (e.g. road tax is lifetime for many vehicles). */
    public static function optionalVehicleExpiryFields(): array
    {
        return ['road_tax_expiry' => __('Road tax')];
    }

    public static function allVehicleExpiryFields(): array
    {
        return self::vehicleExpiryFields() + self::optionalVehicleExpiryFields();
    }

    public static function partTypes(): array
    {
        return ['tyre' => __('Tyre'), 'battery' => __('Battery'), 'other' => __('Other part')];
    }

    public static function partPositions(): array
    {
        return ['FL' => __('Front left'), 'FR' => __('Front right'), 'RL' => __('Rear left'), 'RR' => __('Rear right'),
            'RL2' => __('Rear left (inner)'), 'RR2' => __('Rear right (inner)'), 'spare' => __('Spare'), 'main' => __('Main / only')];
    }

    public static function incidentTypes(): array
    {
        return ['accident' => __('Accident'), 'challan' => __('Traffic challan'), 'damage' => __('Damage'), 'theft' => __('Theft'), 'other' => __('Other')];
    }

    public static function claimStatuses(): array
    {
        return ['none' => __('No claim'), 'filed' => __('Claim filed'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'settled' => __('Settled')];
    }

    public static function ownerRateTypes(): array
    {
        return ['per_km' => __('Per km'), 'per_trip' => __('Per trip'), 'monthly' => __('Fixed per month'), 'revenue_share' => __('% of trip revenue')];
    }

    public static function depreciationMethods(): array
    {
        return ['wdv' => __('Written-down value (WDV)'), 'slm' => __('Straight line (SLM)')];
    }

    public static function payoutMethods(): array
    {
        return ['bank_transfer' => __('Bank transfer'), 'upi' => 'UPI', 'cash' => __('Cash'), 'cheque' => __('Cheque'), 'other' => __('Other')];
    }

    /** Daily driver checklist items (key => label). */
    public static function inspectionItems(): array
    {
        return [
            'tyres' => __('Tyres & spare'), 'brakes' => __('Brakes'), 'lights' => __('Lights & indicators'), 'horn' => __('Horn'),
            'wipers' => __('Wipers & mirrors'), 'fluids' => __('Engine oil & coolant'), 'body' => __('Body (no new damage)'),
            'cleanliness' => __('Clean inside & outside'), 'documents' => __('Papers in vehicle (RC, insurance, PUC)'), 'safety' => __('First-aid kit & fire extinguisher'),
        ];
    }
}
