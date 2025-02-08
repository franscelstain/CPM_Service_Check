<?php

namespace App\Interfaces;

interface NotificationRepositoryInterface
{
    public function deleteNotificationById(int $id): bool;
    public function getActiveNotifications();
    public function getBadge($investorId);
    public function getData($investorId);
    public function getNotificationDetailById($id);
    public function getNotificationIntervals($id);
    public function getNotificationStatus($investorId);
    public function insertNotification(array $data, array $intervals, $manager);
    public function markNotificationAsRead($id);
    public function updateBadgeDefault($investorId);
    public function updateNotification(array $data, array $intervals, $id, $manager);
}