/**
 * RMS Image Optimizer — Frontend Application Logic
 * Vanilla JS, no framework dependencies
 */

'use strict';

// Base URL prefix — works for both http://rms-exif.test/ and http://localhost/rms-exif/
const BASE = window.APP_CONFIG?.basePath ?? '';

// ── State ─────────────────────────────────────────────────────────────────────
const state = {
  currentStep: 1,
  files: [],          // { file, tempKey, origSize, width, height, exif, result }
  activeIdx: 0,       // which file in batch is shown
};

// ── DOM helpers ───────────────────────────────────────────────────────────────
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

// ── On DOM Ready ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initDropZone();
  initQualitySlider();
  initFilenameBuilder();
  initDescCounter();
  initGpsConverter();
  initButtons();
});

// ══════════════════════════════════════════════════ STEP 1: UPLOAD
function initDropZone() {
  const zone = $('#drop-zone');
  const fileIn = $('#file-input');

  zone.addEventListener('click', () => fileIn.click());
  zone.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') fileIn.click(); });

  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    handleFiles([...e.dataTransfer.files]);
  });

  fileIn.addEventListener('change', () => {
    handleFiles([...fileIn.files]);
    fileIn.value = '';
  });

  $('#clear-queue').addEventListener('click', clearQueue);
}

function handleFiles(incoming) {
  const maxBytes = window.APP_CONFIG.maxFileSizeMB * 1024 * 1024;
  const allowed = ['image/jpeg', 'image/png', 'image/webp'];

  incoming.forEach(file => {
    if (!allowed.includes(file.type)) {
      showError('upload-error', `"${file.name}" is not a supported type (JPG, PNG, WEBP only).`);
      return;
    }
    if (file.size > maxBytes) {
      showError('upload-error', `"${file.name}" exceeds the ${window.APP_CONFIG.maxFileSizeMB} MB limit.`);
      return;
    }
    // Avoid duplicates
    if (state.files.some(f => f.file.name === file.name && f.file.size === file.size)) return;

    state.files.push({ file, tempKey: null, origSize: file.size, width: 0, height: 0, exif: {}, result: null });
    addQueueItem(state.files.length - 1);
  });

  updateQueueUI();
}

function addQueueItem(idx) {
  const { file } = state.files[idx];
  const list = $('#queue-list');
  const li = document.createElement('li');
  li.className = 'rms-queue-item';
  li.dataset.idx = idx;

  const thumb = document.createElement('img');
  thumb.className = 'rms-queue-thumb';
  thumb.alt = file.name;
  thumb.src = URL.createObjectURL(file);

  const info = document.createElement('div');
  info.className = 'flex-grow-1 overflow-hidden';
  info.innerHTML = `<div class="rms-queue-name">${escHtml(file.name)}</div>
                    <div class="rms-queue-size">${humanSize(file.size)}</div>`;

  const remove = document.createElement('button');
  remove.className = 'rms-queue-remove';
  remove.title = 'Remove';
  remove.textContent = '×';
  remove.addEventListener('click', () => removeQueueItem(idx));

  li.append(thumb, info, remove);
  list.appendChild(li);
}

function removeQueueItem(idx) {
  state.files.splice(idx, 1);
  rebuildQueueList();
  updateQueueUI();
}

function rebuildQueueList() {
  $('#queue-list').innerHTML = '';
  state.files.forEach((_, i) => addQueueItem(i));
}

function clearQueue() {
  state.files = [];
  $('#queue-list').innerHTML = '';
  updateQueueUI();
}

function updateQueueUI() {
  const count = state.files.length;
  const fq = $('#file-queue');
  if (count > 0) {
    fq.hidden = false;
    $('#queue-count').textContent = `${count} file${count > 1 ? 's' : ''}`;
  } else {
    fq.hidden = true;
  }
  $('#btn-upload').disabled = count === 0;
  hideEl('upload-error');
}

// ── Quality slider ────────────────────────────────────────────────────────────
function initQualitySlider() {
  const slider = $('#quality-slider');
  const label = $('#quality-value');
  slider.addEventListener('input', () => { label.textContent = slider.value + '%'; });
}

// ── Step 1 Upload button ──────────────────────────────────────────────────────
function initButtons() {
  $('#btn-upload').addEventListener('click', doUpload);
  $('#btn-back-1').addEventListener('click', () => goToStep(1));
  $('#btn-process').addEventListener('click', doProcess);
  $('#btn-back-2').addEventListener('click', () => goToStep(2));
  $('#btn-download-single').addEventListener('click', downloadSingle);
  $('#btn-download-zip').addEventListener('click', downloadZip);
  $('#btn-new-image').addEventListener('click', resetApp);
}

async function doUpload() {
  if (state.files.length === 0) return;

  showEl('upload-progress');
  hideEl('upload-error');
  setProgress(10);

  const formData = new FormData();
  state.files.forEach(({ file }) => formData.append('images[]', file));

  try {
    setProgress(40);
    const res = await fetch(`${BASE}/api/upload.php`, { method: 'POST', body: formData });
    setProgress(80);
    const data = await res.json();

    if (data.error) throw new Error(data.error);

    const uploads = data.uploads || [];
    uploads.forEach((up, i) => {
      if (up.success && state.files[i]) {
        state.files[i].tempKey = up.temp_key;
        state.files[i].width = up.width;
        state.files[i].height = up.height;
        state.files[i].origSize = up.size;
        state.files[i].exif = up.exif || {};
        markQueueItem(i, 'ok');
      } else if (up.error && state.files[i]) {
        markQueueItem(i, 'error');
      }
    });

    setProgress(100);
    setTimeout(() => {
      hideEl('upload-progress');
      prefillExifFromFirstImage();
      buildImageTabs();
      goToStep(2);
    }, 300);

  } catch (err) {
    setProgress(0);
    hideEl('upload-progress');
    showError('upload-error', err.message || 'Upload failed. Please try again.');
  }
}

function prefillExifFromFirstImage() {
  if (!state.files[0]) return;
  const exif = state.files[0].exif;
  if (exif.ImageDescription) $('#meta-description').value = exif.ImageDescription;
  if (exif.XPKeywords) $('#meta-keywords').value = exif.XPKeywords;
  if (exif.Artist) $('#meta-artist').value = exif.Artist;
  if (exif.Copyright) $('#meta-copyright').value = exif.Copyright;
  if (exif.City) { $('#gps-city').value = exif.City; }
  if (exif.State) { $('#gps-state').value = exif.State; }
  if (exif.Country) { $('#gps-country').value = exif.Country; }
  if (exif.GPSLatitude) { $('#gps-lat').value = exif.GPSLatitude; updateDMS(); }
  if (exif.GPSLongitude) { $('#gps-lng').value = exif.GPSLongitude; updateDMS(); }
  updateDescCounter();
}

function markQueueItem(idx, status) {
  const li = $(`[data-idx="${idx}"]`, $('#queue-list'));
  if (!li) return;
  if (status === 'ok') li.style.borderColor = '#30d97b';
  if (status === 'error') li.style.borderColor = '#f74f4f';
}

// ══════════════════════════════════════════════════ STEP 2: METADATA

function initFilenameBuilder() {
  $$('.fn-part').forEach(el => el.addEventListener('input', updateFilename));
  $('#fn-custom').addEventListener('input', updateFilename);
}

function updateFilename() {
  const custom = $('#fn-custom').value.trim();
  if (custom) {
    $('#filename-output').textContent = sanitizeFilename(custom) + (custom.endsWith('.jpg') ? '' : '.jpg');
    return;
  }
  const s = sanitizeSlug($('#fn-service').value);
  const c = sanitizeSlug($('#fn-city').value);
  const st = sanitizeSlug($('#fn-state').value);
  const d = sanitizeSlug($('#fn-descriptor').value);
  const parts = [s, c, st, d].filter(Boolean);
  const name = parts.length ? parts.join('-') + '.jpg' : 'service-city-state-descriptor.jpg';
  $('#filename-output').textContent = name;
}

function getSeoFilename() {
  const custom = slugify($('#fn-custom').value.trim());
  if (custom) return custom.endsWith('.jpg') ? custom : custom + '.jpg';
  return $('#filename-output').textContent || 'image.jpg';
}

function initDescCounter() {
  const ta = $('#meta-description');
  ta.addEventListener('input', updateDescCounter);
}
function updateDescCounter() {
  $('#desc-count').textContent = $('#meta-description').value.length;
}

function initGpsConverter() {
  $('#gps-lat').addEventListener('input', updateDMS);
  $('#gps-lng').addEventListener('input', updateDMS);
}

function updateDMS() {
  const lat = parseFloat($('#gps-lat').value);
  const lng = parseFloat($('#gps-lng').value);

  if (isNaN(lat) || isNaN(lng)) { $('#dms-output').textContent = '—'; return; }

  const latDMS = decimalToDMS(lat, 'N', 'S');
  const lngDMS = decimalToDMS(lng, 'E', 'W');
  $('#dms-output').textContent = `${latDMS}  ${lngDMS}`;
}

function decimalToDMS(decimal, posLabel, negLabel) {
  const abs = Math.abs(decimal);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = ((minFloat - min) * 60).toFixed(2);
  const dir = decimal >= 0 ? posLabel : negLabel;
  return `${deg}°${min}'${sec}"${dir}`;
}

// ── Image tabs (batch navigation) ─────────────────────────────────────────────
function buildImageTabs() {
  if (state.files.length <= 1) return;
  ['image-tabs', 'result-tabs'].forEach(id => {
    const container = $(`#${id}`);
    container.innerHTML = '';
    state.files.forEach((f, i) => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm ' + (i === 0 ? 'btn-primary' : 'btn-outline-secondary');
      btn.textContent = `Image ${i + 1}: ${f.file.name.substring(0, 20)}${f.file.name.length > 20 ? '…' : ''}`;
      btn.addEventListener('click', () => {
        state.activeIdx = i;
        $$('#image-tabs button, #result-tabs button').forEach(b => {
          b.className = 'btn btn-sm btn-outline-secondary';
        });
        $$(`#image-tabs button:nth-child(${i + 1}), #result-tabs button:nth-child(${i + 1})`).forEach(b => {
          b.className = 'btn btn-sm btn-primary';
        });
        if (state.currentStep === 3 && f.result) showResult(f.result, i);
      });
      container.appendChild(btn);
    });
  });
}

// ── Process ───────────────────────────────────────────────────────────────────
async function doProcess() {
  const validFiles = state.files.filter(f => f.tempKey);
  if (validFiles.length === 0) {
    showError('process-error', 'No valid uploaded images to process.');
    return;
  }

  showEl('processing-overlay');
  hideEl('process-error');
  $('#btn-process').disabled = true;

  const basePayload = {
    quality: parseInt($('#quality-slider').value),
    resize_preset: $('#resize-preset').value,
    description: $('#meta-description').value,
    keywords: $('#meta-keywords').value,
    artist: $('#meta-artist').value,
    copyright: $('#meta-copyright').value,
    latitude: $('#gps-lat').value,
    longitude: $('#gps-lng').value,
    city: $('#gps-city').value,
    state: $('#gps-state').value,
    country: $('#gps-country').value,
  };

  try {
    const seoBase = getSeoFilename().replace('.jpg', '');

    for (let i = 0; i < state.files.length; i++) {
      const f = state.files[i];
      if (!f.tempKey) continue;

      // Generate unique filename for each image in batch
      const numSuffix = state.files.length > 1 ? `-${i + 1}` : '';
      const seoName = seoBase + numSuffix + '.jpg';

      const payload = { ...basePayload, temp_key: f.tempKey, seo_filename: seoName };

      const res = await fetch(`${BASE}/api/process.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.error) throw new Error(data.error);
      state.files[i].result = data;
    }

    hideEl('processing-overlay');
    $('#btn-process').disabled = false;

    // Show result for first (or active) image
    const firstResult = state.files[state.activeIdx]?.result || state.files.find(f => f.result)?.result;
    if (firstResult) {
      showResult(firstResult, state.activeIdx);
      buildImageTabs();
      goToStep(3);
    }

  } catch (err) {
    hideEl('processing-overlay');
    $('#btn-process').disabled = false;
    showError('process-error', err.message || 'Processing failed. Please try again.');
  }
}

function showResult(data, idx) {
  // Images
  const beforeImg = $('#img-before');
  const afterImg = $('#img-after');

  // Use the original file for "before" preview
  if (state.files[idx]?.file) {
    beforeImg.src = URL.createObjectURL(state.files[idx].file);
  }
  afterImg.src = data.preview_url + '?t=' + Date.now();

  // Meta labels
  $('#before-meta').textContent = `${data.original_size_human} · ${data.original_dimensions.w}×${data.original_dimensions.h}`;
  $('#after-meta').textContent = `${data.optimized_size_human} · ${data.optimized_dimensions.w}×${data.optimized_dimensions.h}`;

  // Savings bar
  const pct = parseFloat(data.savings_percent) || 0;
  $('#savings-label').textContent = pct > 0
    ? `✓ ${pct}% smaller — ${data.original_size_human} → ${data.optimized_size_human}`
    : `Size: ${data.original_size_human} → ${data.optimized_size_human}`;
  setTimeout(() => { $('#savings-fill').style.width = Math.min(pct, 100) + '%'; }, 100);

  // EXIF table
  const exif = data.exif_summary || {};
  const table = $('#exif-table');
  table.innerHTML = '';

  const rows = [
    ['Filename', data.seo_filename],
    ['Description', exif.ImageDescription],
    ['Keywords', exif.Keywords],
    ['GPS', exif.GPS],
    ['City / State', [exif.City, exif.State].filter(Boolean).join(', ')],
    ['Author', exif.Artist],
    ['ExifTool', exif.ExifToolUsed ? '✓ Metadata embedded' : '⚠ ExifTool not available'],
  ];
  rows.forEach(([label, val]) => {
    if (!val) return;
    const dt = document.createElement('dt'); dt.textContent = label;
    const dd = document.createElement('dd'); dd.textContent = val || '';
    table.append(dt, dd);
  });

  // Download filename
  $('#download-filename').textContent = data.seo_filename;

  // Store tempKey for single download
  $('#btn-download-single').dataset.key = data.temp_key;
}

// ── Downloads ─────────────────────────────────────────────────────────────────
function downloadSingle() {
  const key = $('#btn-download-single').dataset.key;
  if (!key) return;
  window.location.href = `${BASE}/api/download.php?key=${key}`;
}

function downloadZip() {
  window.location.href = `${BASE}/api/download-zip.php`;
}

// ── Step Navigation ───────────────────────────────────────────────────────────
function goToStep(n) {
  state.currentStep = n;

  // Panels – class is 'rms-step-panel' (set in index.php)
  $$('.rms-step-panel').forEach((p, i) => {
    p.hidden = (i + 1) !== n;
  });

  // Step nav items – class is 'rms-step' (set in index.php)
  $$('.rms-step').forEach((item, i) => {
    const step = i + 1;
    item.classList.toggle('active', step === n);
    item.classList.toggle('done', step < n);
  });

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Reset ─────────────────────────────────────────────────────────────────────
function resetApp() {
  state.files = [];
  state.activeIdx = 0;
  state.currentStep = 1;
  clearQueue();
  $('#queue-list').innerHTML = '';
  $('#image-tabs').innerHTML = '';
  $('#result-tabs').innerHTML = '';
  ['meta-description', 'meta-keywords', 'meta-artist', 'meta-copyright',
    'gps-lat', 'gps-lng', 'gps-city', 'gps-state', 'gps-country',
    'fn-service', 'fn-city', 'fn-state', 'fn-descriptor', 'fn-custom'].forEach(id => {
      const el = $(`#${id}`);
      if (el) el.value = '';
    });
  updateFilename();
  updateDescCounter();
  $('#dms-output').textContent = '—';
  $('#savings-fill').style.width = '0%';
  hideEl('upload-error');
  hideEl('process-error');
  goToStep(1);
}

// ── Utility ───────────────────────────────────────────────────────────────────
function humanSize(bytes) {
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
}

function sanitizeSlug(str) {
  return str.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function sanitizeFilename(str) {
  let s = sanitizeSlug(str.replace(/\.jpg$/i, ''));
  return s || 'image';
}

function slugify(str) {
  return sanitizeSlug(str);
}

function escHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showError(id, msg) {
  const el = $(`#${id}`);
  if (el) { el.textContent = msg; el.hidden = false; }
}

function showEl(id) { const el = $(`#${id}`); if (el) el.hidden = false; }
function hideEl(id) { const el = $(`#${id}`); if (el) el.hidden = true; }

function setProgress(pct) {
  const bar = $('#progress-bar');
  if (bar) bar.style.width = pct + '%';
  const lbl = $('#progress-label');
  if (lbl) lbl.textContent = pct < 100 ? 'Uploading…' : 'Upload complete!';
}
