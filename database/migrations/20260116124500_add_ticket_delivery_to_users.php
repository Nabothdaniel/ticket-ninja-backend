<?php
use Phinx\Migration\AbstractMigration;

class AddTicketDeliveryToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        if (!$table->hasColumn('ticket_delivery')) {
            $table->addColumn('ticket_delivery', 'enum', [
                'values' => ['Email', 'WhatsApp', 'Telegram', 'Download'],
                'default' => 'Email',
                'after' => 'industry',
                'null' => true
            ])->update();
        }
    }
}
