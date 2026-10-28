<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class VietQrPayload
{
    public function generate(string $bankBin, string $account, string $amount, string $content): string
    {
        if (! preg_match('/^[0-9]{6}$/D', $bankBin) || ! preg_match('/^[A-Z0-9]{1,19}$/D', $account)
            || ! preg_match('/^[1-9][0-9]{0,12}$/D', $amount) || ! preg_match('/^[\x20-\x7E]{1,25}$/D', $content)) {
            throw new InvalidArgumentException('Thông tin tạo QR không hợp lệ.');
        }
        $receiver = $this->field('00', $bankBin).$this->field('01', $account);
        $napas = $this->field('00', 'A000000727').$this->field('01', $receiver).$this->field('02', 'QRIBFTTA');
        $payload = '000201010212'.$this->field('38', $napas).'5303704'.$this->field('54', $amount)
            .'5802VN'.$this->field('62', $this->field('08', $content)).'6304';

        return $payload.$this->checksum($payload);
    }

    private function field(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function checksum(string $payload): string
    {
        $crc = 0xFFFF;
        foreach (unpack('C*', $payload) as $byte) {
            $crc ^= $byte << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1)) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
