<?php
include 'comon/head.php';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/style.css">
</head>

<body>

    <div class="dashboard-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold"><i class="fa-solid fa-share-nodes"></i> Social Master</h1>
            <p class="lead mb-0">Automated Social Media Publishing Dashboard</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if (!empty($status)): ?>
                    <div class="alert <?= strpos($status, '❌') !== false ? 'alert-warning' : 'alert-success' ?> alert-dismissible fade show result-box shadow-sm mb-4" role="alert">
                        <h5 class="alert-heading"><i class="fa-solid fa-clipboard-check me-2"></i>Publishing Results</h5>
                        <hr>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($status)); ?></p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Create New Post
                    </div>
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data">

                            <div class="mb-4">
                                <label for="message" class="form-label fw-bold">Message / Caption</label>
                                <textarea class="form-control bg-light" name="message" id="message" rows="4" placeholder="What do you want to share with your audience?" required></textarea>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="link" class="form-label fw-bold">Attach Link <span class="text-muted fw-normal">(Optional)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-link text-muted"></i></span>
                                        <input type="url" class="form-control bg-light border-start-0" name="link" id="link" placeholder="https://example.com">
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label for="media" class="form-label fw-bold">Upload Media <span class="text-muted fw-normal">(Optional)</span></label>
                                    <input type="file" class="form-control bg-light" name="media" id="media" accept="image/*,video/*">
                                    <div class="form-text">Supported: Images (JPG, PNG) and Videos (MP4)</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Target Platforms</label>
                                <div class="d-flex flex-wrap gap-4 mt-2">
                                    <div class="form-check platform-checkbox fs-5 d-flex align-items-center">
                                        <input class="form-check-input me-2 mt-0" type="checkbox" name="platforms[]" value="facebook" id="plat_fb" checked>
                                        <label class="form-check-label d-flex align-items-center" for="plat_fb">
                                            <i class="fa-brands fa-facebook platform-icon facebook-color"></i> Facebook
                                        </label>
                                    </div>
                                    <div class="form-check platform-checkbox fs-5 d-flex align-items-center">
                                        <input class="form-check-input me-2 mt-0" type="checkbox" name="platforms[]" value="instagram" id="plat_ig" checked>
                                        <label class="form-check-label d-flex align-items-center" for="plat_ig">
                                            <i class="fa-brands fa-instagram platform-icon instagram-color"></i> Instagram
                                        </label>
                                    </div>
                                    <div class="form-check platform-checkbox fs-5 d-flex align-items-center">
                                        <input class="form-check-input me-2 mt-0" type="checkbox" name="platforms[]" value="telegram" id="plat_tg" checked>
                                        <label class="form-check-label d-flex align-items-center" for="plat_tg">
                                            <i class="fa-brands fa-telegram platform-icon telegram-color"></i> Telegram
                                        </label>
                                    </div>
                                    <div class="form-check platform-checkbox fs-5 d-flex align-items-center">
                                        <input class="form-check-input me-2 mt-0" type="checkbox" name="platforms[]" value="x" id="plat_x">
                                        <label class="form-check-label d-flex align-items-center" for="plat_x">
                                            <i class="fa-brands fa-x-twitter platform-icon x-color"></i> X (Twitter)
                                        </label>
                                    </div>
                                    <div class="form-check platform-checkbox fs-5 d-flex align-items-center">
                                        <input class="form-check-input me-2 mt-0" type="checkbox" name="platforms[]" value="linkedin" id="plat_li">
                                        <label class="form-check-label d-flex align-items-center" for="plat_li">
                                            <i class="fa-brands fa-linkedin platform-icon linkedin-color"></i> LinkedIn
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-primary btn-post btn-lg text-white">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Publish Now
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

                <div class="text-center mt-4 text-muted small">
                    &copy; <?php echo date('Y'); ?> Leykun Advertising. All rights reserved.
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>