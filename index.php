<?php
// Inicializar puntos.json si no existe
$puntos_file = 'puntos.json';
if (!file_exists($puntos_file)) {
    file_put_contents($puntos_file, json_encode([
        ['id' => 'tecno', 'coords' => [18.4759788, -69.7261934], 'nombre' => 'Tecno Stream / Piscina La Bruja', 'foto' => null]
    ]));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mapa con menú plegable (JSON)</title>

  <!-- Leaflet (local) -->
  <link rel="stylesheet" href="lib/leaflet.css"/>
  <script src="lib/leaflet.js"></script>

  <!-- Leaflet Routing Machine (local) -->
  <link rel="stylesheet" href="lib/leaflet-routing-machine.css"/>
  <script src="lib/leaflet-routing-machine.js"></script>

  <style>
    html, body { height: 100%; margin: 0; }
    #map { height: 100%; width: 100%; }
    #menuBtn {
      position: absolute; top: 10px; left: 10px; z-index: 1100;
      background: #111827; color: #fff; border: none;
      padding: 10px 15px; border-radius: 8px; cursor: pointer;
      font-size: 16px; transition: background 0.3s;
    }
    #menuBtn:hover { background: #1f2937; }
    #panel {
      position: absolute; top: 60px; left: 10px; z-index: 1100;
      background: rgba(255,255,255,0.98); padding: 15px;
      border-radius: 12px; font-family: Arial, sans-serif; font-size: 14px;
      box-shadow: 0 6px 14px rgba(0,0,0,0.2); max-width: 300px;
      display: none; transition: opacity 0.3s ease;
    }
    #panel.show { display: block; opacity: 1; }
    #panel.hide { display: none; opacity: 0; }
    .photo-popup img { max-width: 240px; border-radius: 10px; display: block; }
    .photo-popup .cap { font-size: 12px; margin-top: 6px; opacity: 0.85; }
    #panel input, #panel select, #panel button {
      margin: 8px 0; width: 100%; padding: 8px; border-radius: 6px;
      border: 1px solid #ddd; box-sizing: border-box;
    }
    #panel button {
      background: #111827; color: #fff; border: none; cursor: pointer;
      transition: background 0.3s;
    }
    #panel button:hover { background: #1f2937; }
    #panel button.delete { background: #e74c3c; }
    #panel button.delete:hover { background: #c0392b; }
    .toolbar {
      position: absolute; bottom: 10px; left: 10px; z-index: 1100;
      background: rgba(255,255,255,0.95); padding: 10px;
      border-radius: 8px; font-size: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
<div id="map"></div>
<button id="menuBtn">☰ Menú</button>
<div id="panel" class="hide">
  <button id="toggleMark">Activar modo marcar</button>
  <hr>
  <label><b>Editar punto:</b></label>
  <select id="editSelect"></select>
  <input id="editName" type="text" placeholder="Nuevo nombre">
  <input id="editPhoto" type="file" accept="image/*">
  <button id="saveEdit">Guardar cambios</button>
  <button id="deletePoint" class="delete">Eliminar punto</button>
  <hr>
  <label><b>Calcular ruta:</b></label>
  <select id="startRoute"></select>
  <span>→</span>
  <select id="endRoute"></select>
  <button id="calcRoute">Calcular</button>
</div>
<div class="toolbar"><span id="status">Listo</span></div>

<script>
  const limites = L.latLngBounds([
    [18.4750, -69.7310],
    [18.4770, -69.7245]
  ]);
  const map = L.map('map', { maxBounds: limites, minZoom: 17, maxZoom: 20 })
               .fitBounds(limites);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20, attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  const markers = {};
  let routingControl = null;
  let markMode = false;

  function makePopup(nombre, foto) {
    return `
      <div class="photo-popup">
        ${foto ? `<img src="${foto}" alt="${nombre}" loading="lazy">` : ""}
        <div class="cap">${nombre}</div>
      </div>`;
  }

  function addMarker(p) {
    const marker = L.marker(p.coords).addTo(map);
    marker.bindPopup(makePopup(p.nombre, p.foto));
    markers[p.id] = marker;
    refreshSelectors();
  }

  function refreshSelectors() {
    const editSel = document.getElementById("editSelect");
    const startSel = document.getElementById("startRoute");
    const endSel = document.getElementById("endRoute");
    [editSel, startSel, endSel].forEach(sel => sel.innerHTML = '<option value="">Selecciona un punto</option>');

    fetch('api.php?action=getPoints')
      .then(response => response.json())
      .then(puntos => {
        puntos.forEach(p => {
          [editSel, startSel, endSel].forEach(sel => {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = p.nombre;
            sel.appendChild(opt);
          });
        });
      });
  }

  function setStatus(msg) {
    const status = document.getElementById("status");
    status.textContent = msg;
    setTimeout(() => { if (status.textContent === msg) status.textContent = "Listo"; }, 3000);
  }

  fetch('api.php?action=getPoints')
    .then(response => response.json())
    .then(puntos => puntos.forEach(p => addMarker(p)));

  document.getElementById("toggleMark").addEventListener("click", () => {
    markMode = !markMode;
    document.getElementById("toggleMark").textContent = markMode ? "Desactivar modo marcar" : "Activar modo marcar";
    setStatus(markMode ? "Modo marcar activado (clic en el mapa)" : "Modo marcar desactivado");
  });

  map.on("click", e => {
    if (markMode) {
      const nombre = prompt("Nombre del punto:", "Nuevo lugar");
      if (!nombre || nombre.trim() === "") {
        setStatus("Nombre inválido, punto no creado");
        return;
      }
      const id = "p" + Date.now();
      fetch('api.php?action=addPoint', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, coords: [e.latlng.lat, e.latlng.lng], nombre: nombre.trim() })
      }).then(response => response.json()).then(data => {
        if (data.success) {
          addMarker({ id, coords: [e.latlng.lat, e.latlng.lng], nombre: nombre.trim(), foto: null });
          setStatus("Punto creado: " + nombre);
        } else {
          setStatus("Error al crear el punto");
        }
      });
    }
  });

  document.getElementById("saveEdit").addEventListener("click", () => {
    const selId = document.getElementById("editSelect").value;
    const nuevoNombre = document.getElementById("editName").value.trim();
    const fotoInput = document.getElementById("editPhoto");

    if (!selId) {
      setStatus("Selecciona un punto para editar");
      return;
    }

    const formData = new FormData();
    formData.append('action', 'editPoint');
    formData.append('id', selId);
    if (nuevoNombre) formData.append('nombre', nuevoNombre);
    if (fotoInput.files.length > 0) formData.append('foto', fotoInput.files[0]);

    fetch('api.php', {
      method: 'POST',
      body: formData
    }).then(response => response.json()).then(data => {
      if (data.success) {
        fetch('api.php?action=getPoint&id=' + selId)
          .then(response => response.json())
          .then(p => {
            markers[p.id].setPopupContent(makePopup(p.nombre, p.foto));
            refreshSelectors();
            setStatus("Punto actualizado");
          });
      } else {
        setStatus("Error al actualizar el punto");
      }
    });
  });

  document.getElementById("deletePoint").addEventListener("click", () => {
    const selId = document.getElementById("editSelect").value;
    if (!selId) {
      setStatus("Selecciona un punto para eliminar");
      return;
    }

    fetch('api.php?action=deletePoint&id=' + selId, {
      method: 'POST'
    }).then(response => response.json()).then(data => {
      if (data.success) {
        map.removeLayer(markers[selId]);
        delete markers[selId];
        refreshSelectors();
        setStatus("Punto eliminado");
      } else {
        setStatus("Error al eliminar el punto");
      }
    });
  });

  document.getElementById("calcRoute").addEventListener("click", () => {
    const startId = document.getElementById("startRoute").value;
    const endId = document.getElementById("endRoute").value;

    if (!startId || !endId) {
      setStatus("Selecciona ambos puntos para la ruta");
      return;
    }
    if (startId === endId) {
      setStatus("Selecciona dos puntos diferentes");
      return;
    }

    fetch('api.php?action=getPoint&id=' + startId)
      .then(res => res.json())
      .then(start => {
        fetch('api.php?action=getPoint&id=' + endId)
          .then(res => res.json())
          .then(end => {
            if (routingControl) {
              map.removeControl(routingControl);
              routingControl = null;
            }

            routingControl = L.Routing.control({
              waypoints: [L.latLng(start.coords), L.latLng(end.coords)],
              routeWhileDragging: false,
              addWaypoints: false,
              draggableWaypoints: false,
              createMarker: () => null,
              lineOptions: { styles: [{ color: '#111827', weight: 4 }] }
            }).addTo(map);

            routingControl.on('routesfound', () => setStatus("Ruta calculada"));
            routingControl.on('routingerror', () => setStatus("Error al calcular la ruta"));
          });
      });
  });

  document.getElementById("menuBtn").addEventListener("click", () => {
    const panel = document.getElementById("panel");
    panel.classList.toggle("show");
    panel.classList.toggle("hide");
  });
</script>
</body>
</html>
