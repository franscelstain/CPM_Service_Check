<?php

namespace App\Services\Utils;

class DateHelper
{
    public static function getEndDate($month, $year)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDay = date('d');

        if ($year == $currentYear && $month == $currentMonth) {
            return date("$year-$month-$currentDay");
        } else {
            return date('Y-m-t', strtotime("$year-$month-01"));
        }
    }

    public static function getStartAndEndDate($month, $year)
    {
        $start_date = date("$year-$month-01");
        $end_date = date('Y-m-t', strtotime($start_date));
        return [$start_date, $end_date];
    }

    public static function getStartAndEndDateMonth($month, $year)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDay = date('d');
        
        $start_date = date('Y-m-d', strtotime("$year-$month-01"));
        if ($year == $currentYear && $month == $currentMonth) {
            $end_date = date("$year-$month-$currentDay");
        } else {
            $end_date = date('Y-m-t', strtotime("$year-$month-01"));
        }

        return [$start_date, $end_date];
    }
}