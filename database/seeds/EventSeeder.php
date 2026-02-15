<?php

use Phinx\Seed\AbstractSeed;
use Ramsey\Uuid\Uuid;

class EventSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [];

        // Create Organizer
        $organizerId = Uuid::uuid4()->toString();
        $organizer = [
            'id' => $organizerId,
            'full_name' => 'Event Organizer',
            'email' => 'organizer@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'organizer',
            'phone' => '+1234567890',
            'interests' => 'Entertainment',
            'is_verified' => 1
        ];

        // Check if organizer exists
        $exists = $this->fetchRow("SELECT * FROM users WHERE email = 'organizer@example.com'");
        if (!$exists) {
            $this->table('users')->insert($organizer)->saveData();
        } else {
             $organizerId = $exists['id'];
        }

        // Create User
        $userId = Uuid::uuid4()->toString();
        $user = [
            'id' => $userId,
            'full_name' => 'John Doe',
            'email' => 'user@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'attendee',
            'phone' => '+0987654321',
            'interests' => 'Technology',
            'is_verified' => 1
        ];

        // Check if user exists
        $exists = $this->fetchRow("SELECT * FROM users WHERE email = 'user@example.com'");
        if (!$exists) {
            $this->table('users')->insert($user)->saveData();
        } else {
            $userId = $exists['id'];
        }

        // Create Events with Unsplash Images
        $eventsData = [
            [
                'title' => 'Global Tech Summit 2026',
                'description' => 'Join the worlds leading tech visionaries for a 3-day summit in Lagos. Experience the future of AI, Blockchain, and IoT.',
                'location' => 'Eko Convention Centre, Lagos',
                'category' => 'Technology',
                'image_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80'
            ],
            [
                'title' => 'Afrobeat Music Festival',
                'description' => 'A vibrant celebration of Afrobeat music featuring top artists from across the continent. Food, drinks, and good vibes.',
                'location' => 'Tafawa Balewa Square, Lagos',
                'category' => 'Music',
                'image_url' => 'https://images.unsplash.com/photo-1459749411177-2a25413fe3dd?auto=format&fit=crop&w=1200&q=80'
            ],
            [
                'title' => 'Startup Founders Workshop',
                'description' => 'Intensive workshop for early-stage founders. Learn about fundraising, product-market fit, and scaling your business.',
                'location' => 'The Zone, Gbagada',
                'category' => 'Business',
                'image_url' => 'https://images.unsplash.com/photo-1515187029135-18ee286d815b?auto=format&fit=crop&w=1200&q=80'
            ],
            [
                'title' => 'Contemporary Art Weekly',
                'description' => 'An exhibition showcasing the finest contemporary art from emerging African artists.',
                'location' => 'Nike Art Gallery, Lekki',
                'category' => 'Art',
                'image_url' => 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5?auto=format&fit=crop&w=1200&q=80'
            ],
            [
                'title' => 'Networking & Cocktails',
                'description' => 'Connect with professionals from various industries in a relaxed atmosphere. Bring your business cards!',
                'location' => 'Radisson Blu Anchorage, VI',
                'category' => 'Networking',
                'image_url' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?auto=format&fit=crop&w=1200&q=80'
            ]
        ];

        foreach ($eventsData as $i => $event) {
            $eventId = Uuid::uuid4()->toString();
            $data[] = [
                'id' => $eventId,
                'title' => $event['title'],
                'description' => $event['description'],
                'date' => date('Y-m-d', strtotime('+' . ($i + 1) . ' weeks')),
                'time' => '10:00:00',
                'location' => $event['location'],
                'category' => $event['category'],
                'ticket_price' => ($i + 1) * 5000,
                'total_tickets' => 100,
                'available_tickets' => 100 - ($i * 5),
                'image_url' => $event['image_url'],
                'organizer_id' => $organizerId
            ];
        }

        // Insert Events first to satisfy FK
        $this->table('events')->insert($data)->saveData();

        // Now add attendees
        $attendeesData = [];
        foreach ($data as $i => $event) {
             // Add tickets for user to first 3 events
            if ($i < 3) {
                // Check if already joined
                $joined = $this->fetchRow("SELECT * FROM attendees WHERE event_id = '{$event['id']}' AND user_id = '$userId'");
                if (!$joined) {
                    $attendeesData[] = [
                        'id' => Uuid::uuid4()->toString(),
                        'event_id' => $event['id'],
                        'user_id' => $userId,
                        'ticket_type' => 'Regular',
                        'ticket_code' => Uuid::uuid4()->toString(),
                        'status' => 'Confirmed'
                    ];
                }
            }
        }
        
        if (!empty($attendeesData)) {
            $this->table('attendees')->insert($attendeesData)->saveData();
        }
    }
}
