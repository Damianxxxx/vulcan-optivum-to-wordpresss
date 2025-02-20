(function() {
    // Funkcja do ustawiania ciasteczek
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires.toUTCString() + "; path=/";
    }

    // Funkcja do pobierania ciasteczek
    function getCookie(name) {
        const matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }

    // Funkcja do przełączania widoczności elementu (menu)
    function toggle(id) {
        const element = document.getElementById(id);
        if (element) {
            element.classList.toggle("show");
        }
    }

    function loadNewPlan(plan) {
        fetch("?plan=" + plan)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, "text/html");
                const newTable = doc.querySelector(".tabela");
                const newTitleElem = doc.querySelector("h2");
                const newTitle = newTitleElem ? newTitleElem.innerText : "";

                if (newTable) {
                    const container = document.getElementById("tabela-container");
                    if (container) {
                        container.innerHTML = newTable.outerHTML;
                    }

                    // Przypisz ponownie event listenery do nowych linków w tabeli
                    document.querySelectorAll(".ajax-link").forEach(function(link) {
                        link.addEventListener("click", function(event) {
                            event.preventDefault();
                            const url = new URL(link.href, window.location.origin);
                            const newPlan = url.searchParams.get("plan");
                            if (newPlan) {
                                loadNewPlan(newPlan);
                            }
                        });
                    });
                }

                // Aktualizacja tytułu strony i nagłówka
                document.title = "Plan lekcji - " + newTitle;
                const header = document.querySelector("h2");
                if (header) {
                    header.innerText = newTitle;
                }

                // Aktualizacja URL bez przeładowania strony
                history.pushState(null, "", "?plan=" + plan);

                // Nadpisanie ciasteczka z wybranym planem (ważnym przez rok)
                setPlanCookie(plan);
            })
            .catch(error => {
                console.error("Error loading new plan:", error);
            });
    }

    // Główna część – uruchamiana po załadowaniu DOM
    document.addEventListener("DOMContentLoaded", function () {
        // Odczytujemy stan rozwiniętych sekcji z ciasteczek
        const expandedSections = JSON.parse(getCookie('expanded_sections') || '[]');

        // Ustawienie event listenerów dla wszystkich nagłówków menu
        document.querySelectorAll('.toggle-header').forEach(function (header) {
            // Pobierz ID sekcji z atrybutu data-section-id
            const sectionId = header.dataset.sectionId || header.classList[1];  // fallback do klasy

            if (sectionId) {
                // Jeśli sekcja jest rozwinięta, dodajemy klasę 'show'
                if (expandedSections.includes(sectionId)) {
                    header.classList.add('show');
                    const section = document.getElementById(sectionId);
                    if (section) {
                        section.classList.add('show');
                    }
                }

                // Obsługuje kliknięcie na nagłówek
                header.addEventListener('click', function () {
                    const section = document.getElementById(sectionId);
                    if (section) {
                        section.classList.toggle('show');
                        // Dodajemy lub usuwamy sekcję z listy rozwiniętych sekcji
                        if (section.classList.contains('show')) {
                            if (!expandedSections.includes(sectionId)) {
                                expandedSections.push(sectionId);
                            }
                        } else {
                            const index = expandedSections.indexOf(sectionId);
                            if (index !== -1) {
                                expandedSections.splice(index, 1);
                            }
                        }
                        setCookie('expanded_sections', JSON.stringify(expandedSections), 365);
                    }
                });
            }
        });

        // Ustawienie event listenerów dla linków w menu
        document.querySelectorAll(".toggle-list a").forEach(function(link) {
            link.addEventListener("click", function(event) {
                event.preventDefault();
                const url = new URL(link.href, window.location.origin);
                const newPlan = url.searchParams.get("plan");
                if (newPlan) {
                    loadNewPlan(newPlan);
                }
            });
        });

        // Ustawienie event listenerów dla linków w tabeli
        document.querySelectorAll(".ajax-link").forEach(function(link) {
            link.addEventListener("click", function(event) {
                event.preventDefault();
                const url = new URL(link.href, window.location.origin);
                const newPlan = url.searchParams.get("plan");
                if (newPlan) {
                    loadNewPlan(newPlan);
                }
            });
        });

        // Opcjonalnie: aktualizacja ciasteczka 'last_plan_update' po 5 sekundach (jeśli istnieje element o ID "plan")
        setTimeout(function() {
            const planSection = document.querySelector('#plan');
            if (planSection) {
                setCookie('last_plan_update', new Date().toISOString(), 365);
            }
        }, 5000);
    });

    // Umożliwienie globalnego dostępu do funkcji, jeśli potrzebne
    window.loadNewPlan = loadNewPlan;
    window.toggle = toggle;
})();
