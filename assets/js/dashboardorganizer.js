    // document.addEventListener("DOMContentLoaded", function() {
    //     const logoutLink = document.getElementById("logoutLink");
    //     const modal = document.getElementById("logoutModal");
    //     const confirmBtn = document.getElementById("confirmLogout");
    //     const cancelBtn = document.getElementById("cancelLogout");

    //     if (!logoutLink || !modal || !confirmBtn || !cancelBtn) return;

    //     logoutLink.addEventListener("click", function(e) {
    //         e.preventDefault();
    //         modal.style.display = "block";
    //     });

    //     confirmBtn.addEventListener("click", function() {
    //         window.location.href = logoutLink.href;
    //     });

    //     cancelBtn.addEventListener("click", function() {
    //         modal.style.display = "none";
    //     });

    //     window.addEventListener("click", function(e) {
    //         if (e.target === modal) {
    //             modal.style.display = "none";
    //         }
    //     });
    // });
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return; // safety check
    
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth', // monthly view
            selectable: true,            // allows clicking a date
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: window.eventsData || [], // load events from global JS variable
            dateClick: function(info) {
                window.location.href = "create_event.php?date=" + info.dateStr;
            }
        });
    
        calendar.render();
    });
    
