<?php

namespace App\Models;

/**
 * Payment Model
 */
class Payment extends BaseModel
{
    protected $table = 'payments';

    /**
     * Find payment by reference
     */
    public function findByReference($reference)
    {
        return $this->whereFirst('reference', $reference);
    }

    /**
     * Mark payment as successful
     */
    public function markAsSuccess($id, $seriesNumber, $payload)
    {
        return $this->update($id, [
            'status' => 'success',
            'series_number' => $seriesNumber,
            'payload' => json_encode($payload),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($id, $payload)
    {
        return $this->update($id, [
            'status' => 'failed',
            'payload' => json_encode($payload),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
