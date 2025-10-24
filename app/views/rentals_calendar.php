<?php
/**
 * File: calendar.php
 * Path: /app/views/backend/rentals/calendar.php
 * Purpose: Rentals calendar view using FullCalendar
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */
?>
<?php $this->layout('backend/layouts/main', ['title' => $title]) ?>

<?php $this->start('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<?php $this->stop() ?>

<?php $this->start('content') ?>

<div class="calendar-page">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-calendar"></i>
            <?= trans('rental.calendar.title') ?>
        </h1>
        <a href="/admin/rentals" class="btn btn-secondary">
            <i class="fas fa-list"></i>
            <?= trans('rental.view_list') ?>
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<?php $this->stop() ?>

<?php $this->start('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/ar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const currentLang = '<?= currentLang()['code'] ?>';
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: currentLang,
        direction: currentLang === 'ar' ? 'rtl' : 'ltr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(info, successCallback, failureCallback) {
            fetch(`/admin/rentals/calendar/data?start=${info.startStr}&end=${info.endStr}`)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(error => failureCallback(error));
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.open(info.event.url, '_blank');
            }
        },
        eventDidMount: function(info) {
            info.el.title = info.event.title;
        }
    });
    
    calendar.render();
});
</script>
<?php $this->stop() ?>
