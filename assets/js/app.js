/**
 * BengalSeva Live GPS & High-Precision Location Auto-Detector
 */
'use strict';

function detectUserLocation(manualTrigger = false) {
    const statusLabel = document.getElementById('currentLocationLabel');
    const jdSelect = document.getElementById('jdCitySelect');

    if (manualTrigger && statusLabel) {
        statusLabel.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-danger"></span> Locating...';
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                // High-precision free reverse geocode without CORS restrictions
                fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
                    .then(r => r.json())
                    .then(data => {
                        const city = data.city || data.locality || 'Kolkata';
                        applyDetectedLocation(city, manualTrigger);
                    })
                    .catch(() => {
                        applyDetectedLocation('Kolkata', manualTrigger);
                    });
            },
            (err) => {
                // If user blocks GPS, fallback automatically
                applyDetectedLocation('Kolkata', manualTrigger);
            },
            { timeout: 6000, enableHighAccuracy: true }
        );
    } else {
        applyDetectedLocation('Kolkata', manualTrigger);
    }
}

function applyDetectedLocation(cityName, manualTrigger = false) {
    const statusLabel = document.getElementById('currentLocationLabel');
    const jdSelect = document.getElementById('jdCitySelect');
    const heroSelect = document.getElementById('heroDistrictSelect');

    if (statusLabel) {
        statusLabel.innerHTML = `📍 <strong>${cityName}</strong>`;
    }

    // Match City to Dropdown Options
    [jdSelect, heroSelect].forEach(selectEl => {
        if (selectEl) {
            for (let i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].text.toLowerCase().includes(cityName.toLowerCase())) {
                    selectEl.selectedIndex = i;
                    break;
                }
            }
        }
    });

    localStorage.setItem('bs_detected_city', cityName);
    if (manualTrigger) {
        alert('Live location set to: ' + cityName);
    }
}

// Auto detect on first load
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('bs_detected_city');
    if (saved) {
        applyDetectedLocation(saved, false);
    } else {
        detectUserLocation(false);
    }
});