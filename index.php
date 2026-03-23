<?php
require_once __DIR__ . '/config.php';
$presetsJson  = json_encode(RESIZE_PRESETS);
$maxSizeMB    = MAX_FILE_SIZE / 1024 / 1024;
$exiftoolOK   = EXIFTOOL_AVAILABLE ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RMS Image Optimizer — Local SEO & Google Business Profile</title>
  <meta name="description" content="Optimize images for Local SEO and Google Business Profile. Add EXIF metadata, GPS geolocation, SEO filenames, and compress without quality loss." />

  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <!-- Custom overrides -->
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_PATH) ?>/assets/css/style.css" />
</head>
<body>

<!-- ══════════════════════════════════ NAVBAR -->
<nav class="navbar navbar-dark rms-navbar sticky-top">
  <div class="container-xl">
    <a class="navbar-brand d-flex align-items-center gap-2" href="#">
      <div class="rms-brand-icon">
        <svg viewBox="0 0 40 40" fill="none" width="36" height="36">
          <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2"/>
          <path d="M12 28l6-8 4 5 3-4 5 7H12z" fill="currentColor" opacity=".35"/>
          <circle cx="27" cy="15" r="3" fill="currentColor" opacity=".7"/>
        </svg>
      </div>
      <div>
        <span class="fw-bold fs-6 d-block lh-1">RMS Image Optimizer</span>
        <small class="text-muted" style="font-size:.68rem;letter-spacing:.03em;">Local SEO · Google Business Profile · EXIF</small>
      </div>
    </a>

    <div id="exiftool-badge">
      <?php if (EXIFTOOL_AVAILABLE): ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
          <i class="bi bi-check-circle-fill me-1"></i>ExifTool Active
        </span>
      <?php else: ?>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>ExifTool Not Found
        </span>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════ MAIN -->
<main class="container-xl py-4">

  <!-- ── STEP WIZARD ────────────────────────────────────── -->
  <div class="rms-stepper mb-4">
    <div class="rms-step active" data-step="1">
      <div class="rms-step-circle">1</div>
      <span class="rms-step-label">Upload</span>
    </div>
    <div class="rms-step-line"></div>
    <div class="rms-step" data-step="2">
      <div class="rms-step-circle">2</div>
      <span class="rms-step-label">SEO &amp; Metadata</span>
    </div>
    <div class="rms-step-line"></div>
    <div class="rms-step" data-step="3">
      <div class="rms-step-circle">3</div>
      <span class="rms-step-label">Preview &amp; Export</span>
    </div>
  </div>

  <!-- ══════════════════════════════ STEP 1: UPLOAD -->
  <section id="step-1" class="rms-step-panel">
    <div class="row g-4">

      <!-- Upload zone -->
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Images</h5>
            <small class="text-muted">JPG, PNG, WEBP · Max <?= $maxSizeMB ?> MB per file</small>
          </div>
          <div class="card-body">

            <div class="rms-drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Upload images drag and drop area">
              <div class="text-center py-4">
                <i class="bi bi-image-alt rms-drop-icon text-primary"></i>
                <p class="fw-semibold mb-1 mt-2">Drag &amp; drop images here</p>
                <p class="text-muted small mb-3">or</p>
                <label class="btn btn-primary btn-sm" for="file-input">
                  <i class="bi bi-folder2-open me-1"></i>Browse Files
                </label>
                <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.webp" multiple hidden />
              </div>
            </div>

            <!-- File queue -->
            <div id="file-queue" hidden class="mt-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted fw-medium" id="queue-count">0 files</small>
                <button class="btn btn-link btn-sm text-danger p-0" id="clear-queue">Clear all</button>
              </div>
              <ul class="list-unstyled mb-0" id="queue-list"></ul>
            </div>

          </div>
        </div>
      </div>

      <!-- Options -->
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Optimization Settings</h5>
          </div>
          <div class="card-body d-flex flex-column gap-3">

            <!-- Quality slider -->
            <div>
              <label class="form-label d-flex justify-content-between">
                <span>JPEG Quality</span>
                <span class="badge bg-primary" id="quality-value">82%</span>
              </label>
              <input type="range" class="form-range" id="quality-slider"
                     min="<?= MIN_JPEG_QUALITY ?>" max="<?= MAX_JPEG_QUALITY ?>" value="<?= DEFAULT_JPEG_QUALITY ?>" />
              <div class="d-flex justify-content-between">
                <small class="text-muted">Smaller file</small>
                <small class="text-muted">Best quality</small>
              </div>
            </div>

            <!-- Resize preset -->
            <div>
              <label class="form-label" for="resize-preset">Resize Preset</label>
              <select id="resize-preset" class="form-select form-select-sm">
                <?php foreach (RESIZE_PRESETS as $key => $p): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($p['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Only downscales — never stretches your image</div>
            </div>

            <!-- Info -->
            <div class="alert alert-info alert-sm py-2 px-3 mb-0" role="alert">
              <i class="bi bi-info-circle me-1"></i>
              All images are converted to <strong>JPEG</strong>. PNG transparency → white background.
            </div>

            <div class="mt-auto">
              <button class="btn btn-primary w-100" id="btn-upload" disabled>
                <i class="bi bi-arrow-right-circle me-1"></i>Upload &amp; Continue
              </button>
            </div>

          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- Progress -->
    <div id="upload-progress" hidden class="mt-3">
      <div class="progress" style="height:6px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progress-bar" style="width:0%"></div>
      </div>
      <small class="text-muted d-block text-center mt-1" id="progress-label">Uploading…</small>
    </div>

    <div class="alert alert-danger mt-3" id="upload-error" hidden role="alert"></div>
  </section>

  <!-- ══════════════════════════════ STEP 2: SEO & METADATA -->
  <section id="step-2" class="rms-step-panel" hidden>

    <!-- Batch image tabs -->
    <div class="rms-tab-row mb-3" id="image-tabs"></div>

    <div class="row g-4">

      <!-- SEO Filename -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>SEO Filename</h5>
            <small class="text-muted">Lowercase · hyphens · no accents</small>
          </div>
          <div class="card-body">

            <div class="row g-2 mb-2">
              <div class="col-sm-6">
                <label class="form-label form-label-sm">Service / Topic</label>
                <input type="text" id="fn-service" class="form-control form-control-sm fn-part" placeholder="move-in-cleaning" />
              </div>
              <div class="col-sm-6">
                <label class="form-label form-label-sm">City</label>
                <input type="text" id="fn-city" class="form-control form-control-sm fn-part" placeholder="Houston" />
              </div>
              <div class="col-sm-6">
                <label class="form-label form-label-sm">State</label>
                <input type="text" id="fn-state" class="form-control form-control-sm fn-part" placeholder="TX" />
              </div>
              <div class="col-sm-6">
                <label class="form-label form-label-sm">Descriptor</label>
                <input type="text" id="fn-descriptor" class="form-control form-control-sm fn-part" placeholder="hardwood-floors" />
              </div>
            </div>

            <div class="rms-filename-preview p-2 mb-3 rounded">
              <small class="text-muted me-1">Generated:</small>
              <code class="text-success" id="filename-output">service-city-state-descriptor.jpg</code>
            </div>

            <div>
              <label class="form-label form-label-sm">Custom filename <span class="text-muted">(overrides builder)</span></label>
              <input type="text" id="fn-custom" class="form-control form-control-sm" placeholder="my-seo-name.jpg" />
            </div>

          </div>
        </div>
      </div>

      <!-- EXIF Metadata -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0"><i class="bi bi-tags me-2 text-primary"></i>EXIF Metadata</h5>
            <?php if (!EXIFTOOL_AVAILABLE): ?>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">ExifTool not found</span>
            <?php endif; ?>
          </div>
          <div class="card-body d-flex flex-column gap-3">

            <div>
              <label class="form-label form-label-sm d-flex justify-content-between">
                <span>Image Description</span>
                <span class="text-muted"><span id="desc-count">0</span>/255</span>
              </label>
              <textarea id="meta-description" class="form-control form-control-sm" rows="3" maxlength="255"
                placeholder="Professional move-in cleaning service in Houston TX — hardwood floors, bathrooms, and kitchen deep clean."></textarea>
            </div>

            <div>
              <label class="form-label form-label-sm">Keywords <span class="text-muted">(comma-separated)</span></label>
              <input type="text" id="meta-keywords" class="form-control form-control-sm"
                placeholder="house cleaning, move-in cleaning, Houston TX, hardwood floors" />
            </div>

            <div class="row g-2">
              <div class="col-sm-6">
                <label class="form-label form-label-sm">Author / Artist</label>
                <input type="text" id="meta-artist" class="form-control form-control-sm" placeholder="Your Name" />
              </div>
              <div class="col-sm-6">
                <label class="form-label form-label-sm">Copyright</label>
                <input type="text" id="meta-copyright" class="form-control form-control-sm" placeholder="© 2026 Company" />
              </div>
            </div>

          </div>
        </div>
      </div>

    </div><!-- /row -->

    <!-- GPS Card -->
    <div class="card mt-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-geo-alt-fill me-2 text-primary"></i>GPS Geolocation
        </h5>
        <small class="text-muted">Embed coordinates for Local SEO — visible in Google Business Profile</small>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-4 col-md-2">
            <label class="form-label form-label-sm">City</label>
            <input type="text" id="gps-city" class="form-control form-control-sm" placeholder="Houston" />
          </div>
          <div class="col-sm-4 col-md-2">
            <label class="form-label form-label-sm">State</label>
            <input type="text" id="gps-state" class="form-control form-control-sm" placeholder="Texas" />
          </div>
          <div class="col-sm-4 col-md-2">
            <label class="form-label form-label-sm">Country</label>
            <input type="text" id="gps-country" class="form-control form-control-sm" placeholder="United States" />
          </div>
          <div class="col-sm-6 col-md-2">
            <label class="form-label form-label-sm">Latitude <span class="text-muted">(decimal)</span></label>
            <input type="number" id="gps-lat" class="form-control form-control-sm" placeholder="29.7604" step="0.000001" min="-90" max="90" />
          </div>
          <div class="col-sm-6 col-md-2">
            <label class="form-label form-label-sm">Longitude <span class="text-muted">(decimal)</span></label>
            <input type="number" id="gps-lng" class="form-control form-control-sm" placeholder="-95.3698" step="0.000001" min="-180" max="180" />
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">DMS Preview</label>
            <div class="rms-dms-output form-control form-control-sm" id="dms-output">—</div>
          </div>
        </div>
        <div class="form-text mt-2">
          <i class="bi bi-info-circle me-1"></i>
          Use <a href="https://maps.google.com" target="_blank" rel="noopener">Google Maps</a> → right-click your location → copy coordinates.
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="d-flex justify-content-between align-items-center mt-4">
      <button class="btn btn-outline-secondary" id="btn-back-1">
        <i class="bi bi-arrow-left me-1"></i>Back
      </button>
      <button class="btn btn-primary px-4" id="btn-process">
        <i class="bi bi-lightning-charge-fill me-1"></i>Optimize &amp; Process
      </button>
    </div>

    <!-- Processing spinner -->
    <div id="processing-overlay" hidden class="text-center py-4">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Processing image…</p>
    </div>

    <div class="alert alert-danger mt-3" id="process-error" hidden role="alert"></div>
  </section>

  <!-- ══════════════════════════════ STEP 3: PREVIEW & EXPORT -->
  <section id="step-3" class="rms-step-panel" hidden>

    <div class="rms-tab-row mb-3" id="result-tabs"></div>

    <div class="row g-4">

      <!-- Before / After -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-images me-2 text-primary"></i>Before / After</h5>
          </div>
          <div class="card-body">

            <div class="row g-3 align-items-start">
              <div class="col-6">
                <span class="badge bg-secondary mb-2">Original</span>
                <img id="img-before" src="" alt="Original image" class="img-fluid rounded border rms-compare-img" />
                <div class="mt-1 small text-muted font-monospace" id="before-meta"></div>
              </div>
              <div class="col-6">
                <span class="badge bg-success mb-2">Optimized</span>
                <img id="img-after" src="" alt="Optimized image" class="img-fluid rounded border rms-compare-img" />
                <div class="mt-1 small text-muted font-monospace" id="after-meta"></div>
              </div>
            </div>

            <!-- Savings bar -->
            <div class="mt-4" id="savings-wrap">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="fw-medium" id="savings-label">—</small>
              </div>
              <div class="progress" style="height:10px;">
                <div class="progress-bar bg-success" id="savings-fill" style="width:0%;transition:width 1s ease;"></div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- EXIF Summary + Download -->
      <div class="col-lg-4 d-flex flex-column gap-4">

        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-card-list me-2 text-primary"></i>EXIF Summary</h5>
          </div>
          <div class="card-body p-0">
            <dl class="mb-0" id="exif-table"></dl>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-download me-2 text-success"></i>Download</h5>
          </div>
          <div class="card-body d-flex flex-column gap-2">
            <code class="d-block rms-filename-preview p-2 rounded small text-success" id="download-filename"></code>
            <button class="btn btn-success w-100" id="btn-download-single">
              <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Image
            </button>
            <button class="btn btn-outline-secondary w-100" id="btn-download-zip">
              <i class="bi bi-file-zip me-1"></i>Download All as ZIP
            </button>
          </div>
        </div>

      </div>
    </div><!-- /row -->

    <!-- Step 3 actions -->
    <div class="d-flex justify-content-between align-items-center mt-4">
      <button class="btn btn-outline-secondary" id="btn-back-2">
        <i class="bi bi-arrow-left me-1"></i>Back to Metadata
      </button>
      <button class="btn btn-outline-primary" id="btn-new-image">
        <i class="bi bi-plus-circle me-1"></i>Optimize Another Image
      </button>
    </div>

  </section>

</main>

<!-- ══════════════════════════════════ FOOTER -->
<footer class="rms-footer border-top mt-5 py-3 text-center text-muted">
  <small>RMS Image Optimizer v<?= APP_VERSION ?> · PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?> · Bootstrap 5 · GD + ExifTool</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.APP_CONFIG = {
    exiftoolAvailable: <?= $exiftoolOK ?>,
    maxFileSizeMB: <?= $maxSizeMB ?>,
    presets: <?= $presetsJson ?>,
    basePath: <?= json_encode(BASE_PATH) ?>
  };
</script>
<script src="<?= htmlspecialchars(BASE_PATH) ?>/assets/js/app.js"></script>
</body>
</html>
