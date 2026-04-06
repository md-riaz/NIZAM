<?php

namespace App\Services\Cdr;

use App\Models\CallDetailRecord;
use App\Models\CdrEnrichment;
use Illuminate\Support\Facades\Log;

class CdrEnrichmentService
{
    /**
     * Country code to country name mapping (E.164 prefixes).
     * A production system would use a database or external API.
     */
    protected const COUNTRY_PREFIXES = [
        '1' => ['country' => 'United States/Canada', 'code' => 'US'],
        '44' => ['country' => 'United Kingdom', 'code' => 'GB'],
        '91' => ['country' => 'India', 'code' => 'IN'],
        '86' => ['country' => 'China', 'code' => 'CN'],
        '81' => ['country' => 'Japan', 'code' => 'JP'],
        '49' => ['country' => 'Germany', 'code' => 'DE'],
        '33' => ['country' => 'France', 'code' => 'FR'],
        '61' => ['country' => 'Australia', 'code' => 'AU'],
        '55' => ['country' => 'Brazil', 'code' => 'BR'],
        '7' => ['country' => 'Russia', 'code' => 'RU'],
        '82' => ['country' => 'South Korea', 'code' => 'KR'],
        '39' => ['country' => 'Italy', 'code' => 'IT'],
        '34' => ['country' => 'Spain', 'code' => 'ES'],
        '52' => ['country' => 'Mexico', 'code' => 'MX'],
        '62' => ['country' => 'Indonesia', 'code' => 'ID'],
        '90' => ['country' => 'Turkey', 'code' => 'TR'],
        '966' => ['country' => 'Saudi Arabia', 'code' => 'SA'],
        '971' => ['country' => 'United Arab Emirates', 'code' => 'AE'],
        '880' => ['country' => 'Bangladesh', 'code' => 'BD'],
        '92' => ['country' => 'Pakistan', 'code' => 'PK'],
        '234' => ['country' => 'Nigeria', 'code' => 'NG'],
        '27' => ['country' => 'South Africa', 'code' => 'ZA'],
        '20' => ['country' => 'Egypt', 'code' => 'EG'],
        '65' => ['country' => 'Singapore', 'code' => 'SG'],
        '60' => ['country' => 'Malaysia', 'code' => 'MY'],
        '63' => ['country' => 'Philippines', 'code' => 'PH'],
        '66' => ['country' => 'Thailand', 'code' => 'TH'],
        '84' => ['country' => 'Vietnam', 'code' => 'VN'],
        '31' => ['country' => 'Netherlands', 'code' => 'NL'],
        '46' => ['country' => 'Sweden', 'code' => 'SE'],
        '47' => ['country' => 'Norway', 'code' => 'NO'],
        '45' => ['country' => 'Denmark', 'code' => 'DK'],
        '358' => ['country' => 'Finland', 'code' => 'FI'],
        '48' => ['country' => 'Poland', 'code' => 'PL'],
        '41' => ['country' => 'Switzerland', 'code' => 'CH'],
        '43' => ['country' => 'Austria', 'code' => 'AT'],
        '32' => ['country' => 'Belgium', 'code' => 'BE'],
        '351' => ['country' => 'Portugal', 'code' => 'PT'],
        '353' => ['country' => 'Ireland', 'code' => 'IE'],
        '64' => ['country' => 'New Zealand', 'code' => 'NZ'],
    ];

    /**
     * Toll-free prefixes for US/CA.
     */
    protected const TOLL_FREE_PREFIXES = ['800', '888', '877', '866', '855', '844', '833'];

    /**
     * Enrich a CDR with destination info, carrier classification, and quality scoring.
     */
    public function enrich(CallDetailRecord $cdr): CdrEnrichment
    {
        $destination = $cdr->destination_number;

        $countryInfo = $this->lookupCountry($destination);
        $numberType = $this->classifyNumberType($destination);
        $qualityScore = $this->calculateQualityScore($cdr);

        // Update CDR quality score if not already set
        if ($cdr->quality_score === null && $qualityScore !== null) {
            $cdr->update(['quality_score' => $qualityScore]);
        }

        $enrichment = CdrEnrichment::updateOrCreate(
            ['cdr_id' => $cdr->id],
            [
                'destination_country' => $countryInfo['country'] ?? null,
                'destination_city' => null, // Would require a more detailed lookup service
                'carrier_name' => null, // Would require an external carrier lookup API
                'number_type' => $numberType,
                'geolocation' => null,
                'enriched_at' => now(),
            ]
        );

        Log::debug('CDR enriched', [
            'cdr_id' => $cdr->id,
            'uuid' => $cdr->uuid,
            'country' => $countryInfo['country'] ?? 'unknown',
            'number_type' => $numberType,
        ]);

        return $enrichment;
    }

    /**
     * Lookup country from destination number using longest prefix match.
     */
    public function lookupCountry(string $number): array
    {
        // Strip leading + or 00
        $normalized = ltrim($number, '+');
        if (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        // Try longest prefix match (up to 4 digits)
        for ($len = min(4, strlen($normalized)); $len >= 1; $len--) {
            $prefix = substr($normalized, 0, $len);
            if (isset(self::COUNTRY_PREFIXES[$prefix])) {
                return self::COUNTRY_PREFIXES[$prefix];
            }
        }

        return ['country' => null, 'code' => null];
    }

    /**
     * Classify the number type based on patterns.
     */
    public function classifyNumberType(string $number): string
    {
        $normalized = ltrim($number, '+');

        // Check for US/CA toll-free
        if (strlen($normalized) >= 4) {
            $areaCode = substr($normalized, 1, 3); // Skip country code '1'
            if (str_starts_with($normalized, '1') && in_array($areaCode, self::TOLL_FREE_PREFIXES)) {
                return 'toll_free';
            }
        }

        // Check for short codes (typically 3-6 digits, no country code)
        if (strlen($number) <= 6 && is_numeric($number)) {
            return 'voip'; // Short codes are often VoIP services
        }

        // Default classification based on length
        // This is a simplified heuristic; production would use HLR/MNP lookup
        return 'unknown';
    }

    /**
     * Calculate a quality score (0-100) from RTP metrics.
     */
    public function calculateQualityScore(CallDetailRecord $cdr): ?int
    {
        $mos = $cdr->mos_score ? (float) $cdr->mos_score : null;
        $packetLoss = $cdr->packet_loss ? (float) $cdr->packet_loss : null;
        $jitter = $cdr->jitter;

        // If no quality data available, return null
        if ($mos === null && $packetLoss === null && $jitter === null) {
            return null;
        }

        $score = 100;

        // MOS-based scoring (1.0 = worst, 5.0 = best)
        if ($mos !== null) {
            // Scale MOS (1-5) to 0-100
            $mosScore = max(0, min(100, (($mos - 1) / 4) * 100));
            $score = $mosScore; // MOS is the primary quality indicator
        }

        // Penalty for packet loss (each 1% reduces score by 5 points)
        if ($packetLoss !== null && $packetLoss > 0) {
            $score -= min(50, $packetLoss * 5);
        }

        // Penalty for jitter (each 10ms above 30ms reduces score by 5 points)
        if ($jitter !== null && $jitter > 30) {
            $score -= min(30, (($jitter - 30) / 10) * 5);
        }

        return max(0, min(100, (int) round($score)));
    }
}
