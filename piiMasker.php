<?php
/**
 * PiiMasker - GDPR Article 25: Data Protection by Design
 * Masks personally identifiable information fields by default.
 */
class PiiMasker
{
    private array $piiFields;

    public function __construct(array $piiFields)
    {
        // Normalize to lowercase for case-insensitive comparison
        $this->piiFields = array_map('strtolower', $piiFields);
    }

    /**
     * Mask a single value based on its type.
     */
    public function maskValue(string $value, string $fieldName): string
    {
        $lowerField = strtolower($fieldName);

        if (!$this->isPiiField($lowerField)) {
            return $value;
        }

        // Email: show first 2 chars + domain
        if (strpos($lowerField, 'email') !== false || strpos($lowerField, 'mail') !== false) {
            return $this->maskEmail($value);
        }

        // Phone: show last 4 digits
        if (strpos($lowerField, 'phone') !== false || strpos($lowerField, 'mobile') !== false) {
            return $this->maskPhone($value);
        }

        // Name fields: show first initial only
        if (
            strpos($lowerField, 'name') !== false ||
            strpos($lowerField, 'first') !== false ||
            strpos($lowerField, 'last') !== false
        ) {
            return $this->maskName($value);
        }

        // IP address: mask last octet
        if (strpos($lowerField, 'ip') !== false) {
            return preg_replace('/\.\d+$/', '.xxx', $value);
        }

        // Default: show first 2 chars, mask rest
        return $this->maskGeneric($value);
    }

    /**
     * Mask an entire row of data.
     */
    public function maskRow(array $row, array $headers): array
    {
        $masked = [];
        foreach ($headers as $i => $header) {
            $value = $row[$i] ?? '';
            $masked[] = $this->maskValue($value, $header);
        }
        return $masked;
    }

    public function isPiiField(string $fieldName): bool
    {
        $lower = strtolower($fieldName);
        foreach ($this->piiFields as $pii) {
            if ($lower === $pii || strpos($lower, $pii) !== false) {
                return true;
            }
        }
        return false;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return str_repeat('*', strlen($email));
        }
        $local = substr($parts[0], 0, 2) . str_repeat('*', max(0, strlen($parts[0]) - 2));
        return $local . '@' . $parts[1];
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($phone));
        }
        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }

    private function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        return strtoupper(substr($name, 0, 1)) . str_repeat('*', max(0, strlen($name) - 1));
    }

    private function maskGeneric(string $value): string
    {
        $len = strlen($value);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 2) . str_repeat('*', $len - 2);
    }
}
