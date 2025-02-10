<?php

namespace App\Repositories;

use App\Interfaces\NotificationRepositoryInterface;
use App\Models\Administrative\Notification\Notification;
use App\Models\Administrative\Notification\NotificationInterval;
use App\Models\Users\Category;


class NotificationRepository implements NotificationRepositoryInterface
{
    public function deleteNotificationById(int $id): bool
    {
        // Update notification is_active menjadi 'No'
        $notificationUpdated = Notification::where('id', $id)->update(['is_active' => 'No']);

        // Jika tidak ada notification yang diupdate, return false
        if ($notificationUpdated === 0) {
            return false;
        }

        // Update notification interval is_active menjadi 'No'
        NotificationInterval::where('notif_id', $id)->update(['is_active' => 'No']);
        
        return true;
    }

    public function getActiveNotifications()
    {
        // Ambil semua notifikasi yang aktif
        return Notification::where('is_active', 'Yes')
            ->get()
            ->map(function ($notification) {
                // Dekode JSON dari assign_to
                $assignToIds = json_decode($notification->assign_to, true);  // Penanganan JSON

                // Query Category berdasarkan usercategory_id yang ada di assign_to
                $assignToNames = !empty($assignToIds) 
                    ? Category::whereIn('usercategory_id', $assignToIds)
                        ->where('is_active', 'Yes')
                        ->pluck('usercategory_name')
                        ->implode(', ')
                    : '';

                // Return data yang sudah dirapikan
                return [
                    'id'            => $notification->id,
                    'title'         => $notification->title,
                    'assign_to'     => $assignToNames,
                    'notif_code'    => $notification->notif_code,
                    'notif_mail'    => $notification->notif_mail,
                    'notif_mobile'  => $notification->notif_mobile,
                    'notif_web'     => $notification->notif_web,
                ];
            });
    }

    // Ambil badge notifikasi berdasarkan investor ID
    public function getBadge($investorId)
    {
        return Notification::select('h_notification_investor.notif_status_batch')
            ->where('h_notification_investor.investor_id', $investorId)
            ->where('h_notification_investor.notif_status_batch', 'f')
            ->get();
    }

    public function getData($investorId)
    {
        // Ambil data notifikasi investor
        $data = Notification::select('h_notification_investor.*')
            ->join('u_investors as ui', 'h_notification_investor.investor_id', '=', 'ui.investor_id')
            ->where('ui.investor_id', $investorId)
            ->get();

        // Ambil badge notifikasi berdasarkan status batch
        $badge = Notification::select('h_notification_investor.notif_status_batch')
            ->where('h_notification_investor.investor_id', $investorId)
            ->where('h_notification_investor.notif_status_batch', 'f')
            ->get();

        // Kembalikan data dan badge
        return ['list' => $data, 'badge' => $badge];
    }

    // Method untuk mendapatkan detail notifikasi berdasarkan ID
    public function getNotificationDetailById($id)
    {
        $notification = Notification::where([['id', $id], ['is_active', 'Yes']])->first();

        if (!$notification) {
            return null;  // Null akan ditangani di level repository
        }

        return $notification;
    }

    public function getNotificationIntervals($id)
    {
        // Jalankan query untuk mendapatkan interval notifikasi
        $qry = NotificationInterval::where([['notif_id', $id], ['is_active', 'Yes']])->get();

        // Jika data ada, kembalikan hasilnya, jika tidak kembalikan array kosong
        return $qry->count() > 0 ? $qry : [];
    }

    // Ambil status notifikasi yang belum dibaca untuk investor yang sedang login
    public function getNotificationStatus($investorId)
    {
        return Notification::select('h_notification_investor.notif_status')
            ->where('h_notification_investor.investor_id', $investorId)
            ->where('h_notification_investor.notif_status', 'f')
            ->get();
    }

    public function insertNotification($data, $intervals, $manager)
    {
        // Simpan data notifikasi ke database
        $data['created_by'] = $manager->user;
        $data['created_host'] = $manager->ip;

        $notification = Notification::create($data);

        // Proses interval notifikasi
        $this->processNotificationIntervals($notification->id, $intervals, $manager);

        return $notification;
    }
    
    // Update status notifikasi sebagai sudah dibaca
    public function markNotificationAsRead($id)
    {
        return Notification::where('id', $id)
            ->update(['notif_status' => 't', 'notif_status_batch' => 't']);
    }
    
    protected function processNotificationIntervals($id, $intervals, $manager)
    {
        // Menonaktifkan interval notifikasi sebelumnya
        NotificationInterval::where('notif_id', $id)->update(['is_active' => 'No']);

        // Pastikan interval memiliki data yang valid
        $reminder = $intervals['reminder'] ?? [];
        $c_reminder = $intervals['count_reminder'] ?? [];
        $continuous = $intervals['continuous'] ?? [];

        for ($i = 0; $i < count($reminder); $i++) {
            // Jika reminder tidak valid, skip iterasi ini
            if (empty($reminder[$i])) {
                continue;
            }

            // Cari interval yang ada, jika tidak ditemukan maka hasilnya null
            $query = NotificationInterval::where('notif_id', $id)
                    ->where('reminder', $reminder[$i]);

            if ($reminder[$i] == 'H') {
                $query->where('count_reminder', $c_reminder[$i] ?? null);
            }

            $intvl = $query->first();
            $act = empty($intvl) ? 'created' : 'updated';

            $data = [
                'notif_id'       => $id,
                'reminder'       => $reminder[$i],
                'count_reminder' => !empty($c_reminder[$i]) ? $c_reminder[$i] : null,
                'continuous'     => $continuous[$i] ? 't' : 'f',
                'is_active'      => 'Yes'
            ];

            // Tambahkan informasi created_by atau updated_by berdasarkan $act
            if ($act == 'updated') {
                $data['updated_by'] = $manager->user;
                $data['updated_host'] = $manager->ip;
            } else {
                $data['created_by'] = $manager->user;
                $data['created_host'] = $manager->ip;
            }

            // Jika interval tidak ditemukan, buat baru; jika ada, update
            if (empty($intvl)) {
                NotificationInterval::create($data);
            } else {
                NotificationInterval::where('id', $intvl->id)->update($data);
            }
        }
    }

    // Update status badge default untuk investor
    public function updateBadgeDefault($investorId)
    {
        return Notification::where('investor_id', $investorId)
            ->update(['notif_status_batch' => 't']);
    }
    
    public function updateNotification($data, $intervals, $id, $manager)
    {
        // Cari notifikasi berdasarkan ID
        $notification = Notification::where('id', $id)->where('is_active', 'Yes')->first();

        // Cek apakah $notification ditemukan
        if (!$notification) {
            return ['error_msg' => ['Notification not found'], 'error_code' => 404];
        }

        // Update data notifikasi di database
        $data['updated_by'] = $manager->user;
        $data['updated_host'] = $manager->ip;

        Notification::where('id', $id)->update($data);

        // Proses interval notifikasi
        $this->processNotificationIntervals($id, $intervals, $manager);

        return Notification::find($id);
    }
}