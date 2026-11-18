<?php

namespace App\Enums;

enum DocumentType: string
{
    case GstCertificate = 'gst_certificate';
    case PanCard = 'pan_card';
    case BusinessRegistration = 'business_registration';
    case OwnerIdProof = 'owner_id_proof';
    case AddressProof = 'address_proof';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GstCertificate => __('GST certificate'),
            self::PanCard => __('PAN card'),
            self::BusinessRegistration => __('Business registration / Udyam'),
            self::OwnerIdProof => __('Owner ID proof (Aadhaar / Passport / DL)'),
            self::AddressProof => __('Business address proof'),
            self::Other => __('Other document'),
        };
    }

    /** Documents an operator must upload for KYC. */
    public static function required(): array
    {
        return [self::PanCard, self::OwnerIdProof, self::AddressProof];
    }
}
