<?php
if (isset($_GET['beacon'])) {
$aprs_server = 'rotate.aprs2.net';
$aprs_port = 14580;
$callsign = 'CALLS1GN-2';
$passcode = '00000'; // APRS Passcode for Callsign use: https://willamettevalleymesh.net/aprs-passcode/ to make

$gps = explode(",", $_GET['beacon']);


$lat = $gps[0];
$lon = $gps[1];

function generate_aprs_passcode($callsign) {
    if (preg_match('/^[a-zA-Z0-9-]+$/', $callsign)) {
        $callsign = strtoupper($callsign);
        $tmp_code = 29666;
        $i = 0;

        while ($i < strlen($callsign)) {
            $tmp_code ^= ord(substr($callsign, $i, 1)) * 256;
            $tmp_code ^= ord(substr($callsign, $i + 1, 1));
            $i += 2;
        }

        $passcode = $tmp_code & 32767;
        return sprintf("%05d", $passcode);
    } else {
        return "";
    }
}


if ($_COOKIE["aprspass"] != generate_aprs_passcode(explode("-", $_COOKIE["callsign"])[0])) {
	die("Wrong APRS Passcode");
}

$login_packet = sprintf(
    'user %s pass %s vers APRS Web Beacon 0.1 filter m/1',
    $callsign,
    $passcode
);

$position_packet = sprintf(
    '%s>APRS,TCPIP*,qAC,%s:=%02d%05.2f%s/%03d%05.2f%s$/A=000001 APRS Web Beacon',
    $_COOKIE["callsign"],
    $callsign,
    floor(abs($lat)),
    (fmod(abs($lat), 1) * 60),
    ($lat >= 0) ? 'N' : 'S',
    floor(abs($lon)),
    (fmod(abs($lon), 1) * 60),
    ($lon >= 0) ? 'E' : 'W'
);

$socket = fsockopen($aprs_server, $aprs_port, $errno, $errstr, 10);
if (!$socket) {
    die("Failed to connect to APRS-IS server: $errno - $errstr\n");
}
fwrite($socket, $login_packet . "\n");
$response = fread($socket, 1024); // Adjust buffer size as needed
fwrite($socket, $position_packet . "\n");
$response = fread($socket, 1024); // Adjust buffer size as needed
fwrite($socket, $position_packet . "\n");
fclose($socket);
echo "OK";
    die();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WVMN APRS Web Beacon</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
body, html {
    margin: 0;
    padding: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-size: 18px; /* Base font size for better readability */
    background-color: #121212; /* Dark background */
    color: #ffffff; /* Light text color */
}

#content {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s;
}

#map, #settings {
    width: 100%;
    height: 100%;
    display: none;
    padding: 20px; /* Padding for settings page */
}

#map.active, #settings.active {
    display: block;
}

#buttons {
    display: flex;
    justify-content: space-around;
    background-color: #1f1f1f; /* Dark background for buttons container */
    border-top: 1px solid #333333; /* Slightly lighter border */
    padding: 20px; /* Increased padding for buttons container */
}

button {
    flex: 1;
    padding: 20px; /* Padding for buttons */
    margin: 0 10px; /* Margin between buttons */
    font-size: 20px; /* Font size for buttons */
    border: none;
    background-color: #007bff; /* Button background color */
    color: white; /* Button text color */
    border-radius: 10px; /* Border-radius for buttons */
    cursor: pointer;
    transition: background-color 0.3s;
}

button:active {
    background-color: #0056b3; /* Darker background when active */
}

#settings label {
    display: block;
    margin: 15px 0 5px;
}

#settings input, #settings select {
    width: calc(100% - 20px);
    padding: 10px;
    font-size: 18px;
    margin-bottom: 10px;
    background-color: #333333; /* Dark background for input fields */
    color: #ffffff; /* Light text color for input fields */
    border: 1px solid #555555; /* Border color for input fields */
    border-radius: 5px;
}

    </style>
</head>
<body>
    <div id="content">
        <div id="map" class="active"></div>
        <div id="settings">
            <h2>Settings</h2>
            <label for="callsign">Callsign:</label>
            <input type="text" id="callsign" name="callsign">

            <label for="aprspass">APRS Passcode:</label>
            <input type="password" id="aprspass" name="aprspass">

            <label for="updateInterval">Update Interval:</label>
            <select id="updateInterval">
                <option value="Disabled">Disabled</option>
                <option value="15000">15 Sec (Hwy Mode)</option>
                <option value="30000">30 Sec</option>
                <option value="60000">1 Min</option>
                <option value="120000">2 Min</option>
                <option value="180000">3 Min</option>
                <option value="300000">5 Min</option>
                <option value="600000">10 Min</option>
                <option value="900000">15 Min</option>
                <option value="1800000">30 Min</option>
                <option value="3600000">1 Hr</option>
            </select>

            <label for="mapType">Map Type:</label>
            <select id="mapType">
                <option value="osm">OpenStreetMap (OSM)</option>
                <option value="satellite">Satellite</option>
            </select>
	    <br /><br /><span><center><a href="https://github.com/thegeekoftheworld/aprswebbeacon/">PHP APRS WebBeacon by K9RCP</a></center></span>
        </div>
    </div>
    <div id="buttons">
        <button id="beaconBtn">Beacon APRS</button>
        <button id="settingsBtn">Show Settings</button>
        <button id="closeBtn" style="display: none;">Close</button>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        const beaconBtn = document.getElementById('beaconBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        const closeBtn = document.getElementById('closeBtn');
        const mapDiv = document.getElementById('map');
        const settingsDiv = document.getElementById('settings');
        const buttonsDiv = document.getElementById('buttons');
        const updateInterval = document.getElementById('updateInterval');
        const mapType = document.getElementById('mapType');
        const callsignInput = document.getElementById('callsign');
        const aprspassInput = document.getElementById('aprspass');

        let map;
        let marker;
        let updateIntervalId;
        let lat, lon;

        function initializeMap(latitude, longitude, mapTypeValue) {
            map = L.map('map').setView([latitude, longitude], 18);

            let tileLayerUrl;
            switch(mapTypeValue) {
                case 'satellite':
                    tileLayerUrl = 'https://tileserver.willamettevalleymesh.net/satellite/tiles/{z}/{x}/{y}.png';
                    break;
                case 'osm':
                default:
                    tileLayerUrl = 'https://tileserver.willamettevalleymesh.net/osm/tiles/{z}/{x}/{y}.png';
                    break;
            }
            L.tileLayer(tileLayerUrl, {
                maxZoom:18,
            }).addTo(map);

            marker = L.marker([latitude, longitude]).addTo(map);
        }

        function updateMap(latitude, longitude) {
            map.setView([latitude, longitude], 18);
            marker.setLatLng([latitude, longitude]);
        }

        function beacon_aprs() {
            getLocation();
            if (!lat || !lon) return;

            fetch(`?beacon=${lat},${lon}`)
                .then(response => response.text())
                .then(text => {
                    if (text.trim() !== "OK") {
                        alert("Error: Beacon APRS failed.");
                    } else {
                        updateMap(lat, lon); // Center map to beacon location
                    }
                })
                .catch(error => {
                    alert("Error: Beacon APRS failed.");
                });
        }

        beaconBtn.addEventListener('click', () => {
            beacon_aprs();
        });

        settingsBtn.addEventListener('click', () => {
            mapDiv.classList.remove('active');
            settingsDiv.classList.add('active');
            beaconBtn.style.display = 'none';
            settingsBtn.style.display = 'none';
            closeBtn.style.display = 'block';
        });

        closeBtn.addEventListener('click', () => {
            settingsDiv.classList.remove('active');
            mapDiv.classList.add('active');
            closeBtn.style.display = 'none';
            beaconBtn.style.display = 'block';
            settingsBtn.style.display = 'block';
        });

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    lat = position.coords.latitude;
                    lon = position.coords.longitude;
                    if (!map) {
                        const savedMapType = getCookie("map_type") || 'osm';
                        initializeMap(lat, lon, savedMapType);
                    } else {
                        updateMap(lat, lon);
                    }
                });
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }

        function setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + d.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        }

        function getCookie(name) {
            const cname = name + "=";
            const decodedCookie = decodeURIComponent(document.cookie);
            const ca = decodedCookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(cname) === 0) {
                    return c.substring(cname.length, c.length);
                }
            }
            return "";
        }

        function updateIntervalChanged() {
            const value = updateInterval.value;
            setCookie("update_time", value, 365);

            clearInterval(updateIntervalId);
            if (value !== "Disabled") {
                updateIntervalId = setInterval(beacon_aprs, parseInt(value));
            }
        }

        function mapTypeChanged() {
            const value = mapType.value;
            setCookie("map_type", value, 365);
            if (map) {
                // Reinitialize map with new map type
                map.remove();
                initializeMap(lat, lon, value);
		location.reload();
            }
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            getLocation();

            const savedInterval = getCookie("update_time");
            if (savedInterval) {
                updateInterval.value = savedInterval;
                if (savedInterval !== "Disabled") {
                    updateIntervalChanged();
                }
            }

            const savedMapType = getCookie("map_type");
            if (savedMapType) {
                mapType.value = savedMapType;
            }

            const savedCallsign = getCookie("callsign");
            if (savedCallsign) {
                callsignInput.value = savedCallsign;
            }

            const savedAprspass = getCookie("aprspass");
            if (savedAprspass) {
                aprspassInput.value = savedAprspass;
            }
        });

        updateInterval.addEventListener('change', updateIntervalChanged);
        mapType.addEventListener('change', mapTypeChanged);

        callsignInput.addEventListener('change', () => {
            setCookie("callsign", callsignInput.value, 365);
        });

        aprspassInput.addEventListener('change', () => {
            setCookie("aprspass", aprspassInput.value, 365);
        });
    </script>
</body>
</html>
