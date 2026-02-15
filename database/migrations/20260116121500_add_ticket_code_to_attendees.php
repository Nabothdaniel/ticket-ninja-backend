<?php
use Phinx\Migration\AbstractMigration;

class AddTicketCodeToAttendees extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('attendees');
        if (!$table->hasColumn('ticket_code')) {
            $table->addColumn('ticket_code', 'string', ['limit' => 100, 'null' => true, 'after' => 'ticket_type'])
                  ->update();
        }
        
        // Add ticket_status as well if missing, as used in dashboard
        if (!$table->hasColumn('status')) {
             $table->addColumn('status', 'enum', ['values' => ['Confirmed', 'Used', 'Cancelled'], 'default' => 'Confirmed', 'after' => 'ticket_code'])
                  ->update();
        }
    }
}
