<?php
// index.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Handle AJAX details request immediately at the top to prevent output contamination
if (isset($_GET['get_details']) && isset($_GET['book_id'])) {
    $bookId = (int)$_GET['book_id'];
    try {
        $db = getDBConnection();
        // Fetch book info
        $stmt = $db->prepare("SELECT t.*, u.name as seller_name, u.email as seller_email, u.phone as seller_phone, u.university as seller_university, u.department as seller_department, u.average_rating as seller_rating, u.profile_image as seller_image
                              FROM textbooks t
                              JOIN users u ON t.user_id = u.id
                              WHERE t.id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            echo '<div class="alert alert-danger">Listing not found.</div>';
            exit;
        }

        // Fetch multiple images
        $stmtImg = $db->prepare("SELECT image_path FROM textbook_images WHERE textbook_id = ?");
        $stmtImg->execute([$bookId]);
        $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
        
        $mainImg = !empty($images) ? $images[0] : null;

        ?>
        <div class="row">
            <!-- Left Side: Images -->
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="listing-gallery">
                    <?php if ($mainImg): ?>
                        <img src="<?php echo sanitize($mainImg); ?>" id="modal-main-gallery" class="gallery-main rounded" alt="Book Image">
                    <?php else: ?>
                        <div class="bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-secondary gallery-main rounded">
                            <i class="bi bi-book-half" style="font-size: 6rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($images as $idx => $path): ?>
                            <img src="<?php echo sanitize($path); ?>" 
                                 class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                 onclick="selectGalleryThumb('<?php echo sanitize($path); ?>', this)" 
                                 alt="Thumbnail">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: Details -->
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="mb-0 text-primary fw-bold"><?php echo sanitize($book['title']); ?></h3>
                    <span class="badge badge-custom badge-<?php echo strtolower($book['exchange_type']); ?>">
                        <?php echo strtoupper($book['exchange_type']); ?>
                    </span>
                </div>
                
                <p class="text-secondary fs-5 mb-3">By <?php echo sanitize($book['author']); ?></p>
                
                <div class="mb-3">
                    <?php if ($book['exchange_type'] == 'sell'): ?>
                        <h4 class="text-success fw-bold">$<?php echo number_format($book['price'], 2); ?></h4>
                    <?php elseif ($book['exchange_type'] == 'rent'): ?>
                        <h4 class="text-cyan fw-bold">$<?php echo number_format($book['price'], 2); ?> <small class="text-secondary small">/ semester</small></h4>
                    <?php else: ?>
                        <h4 class="text-gradient fw-bold"><?php echo ucfirst($book['exchange_type']); ?></h4>
                    <?php endif; ?>
                </div>

                <!-- Attributes Table -->
                <table class="table table-dark table-borderless bg-transparent small mb-4">
                    <tbody>
                        <tr>
                            <td class="text-secondary ps-0 py-1" style="width: 120px;">ISBN:</td>
                            <td class="text-white py-1" style="color: var(--text-primary) !important;"><?php echo sanitize($book['isbn'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary ps-0 py-1">Edition:</td>
                            <td class="text-white py-1" style="color: var(--text-primary) !important;"><?php echo sanitize($book['edition'] ?: 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary ps-0 py-1">Condition:</td>
                            <td class="text-white py-1">
                                <span class="badge badge-custom badge-<?php echo strtolower($book['condition']); ?>">
                                    <?php echo sanitize($book['condition']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-secondary ps-0 py-1">Subject:</td>
                            <td class="text-white py-1" style="color: var(--text-primary) !important;"><?php echo sanitize($book['subject'] ?: 'N/A'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="text-secondary small fw-bold uppercase mb-2">Description</h6>
                <p class="text-secondary small mb-4" style="line-height: 1.6; white-space: pre-line;">
                    <?php echo sanitize($book['description'] ?: 'No description provided.'); ?>
                </p>

                <!-- Owner Card -->
                <div class="card bg-input border-border mb-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <?php if ($book['seller_image']): ?>
                                <img src="<?php echo sanitize($book['seller_image']); ?>" class="rounded-circle me-3" style="width: 48px; height: 48px; object-fit: cover;" alt="Seller Photo">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-3" style="width: 48px; height: 48px; font-weight: 600;">
                                    <?php echo strtoupper(substr($book['seller_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1 min-width-0">
                                <h6 class="text-primary mb-0 text-truncate fw-bold"><?php echo sanitize($book['seller_name']); ?></h6>
                                <p class="text-secondary small mb-0 text-truncate"><i class="bi bi-geo-alt me-1 text-gradient"></i><?php echo sanitize($book['seller_university']); ?></p>
                            </div>
                            <div class="text-end">
                                <div class="star-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <span class="text-primary fw-bold" style="color: var(--text-primary) !important;"><?php echo number_format($book['seller_rating'], 1); ?></span>
                                </div>
                                <span class="text-secondary small">Seller Rating</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="d-grid">
                    <?php if (!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-primary-gradient py-2">Log In to Request Textbook</a>
                    <?php elseif (getCurrentUserId() === (int)$book['user_id']): ?>
                        <a href="user/listings.php?action=edit&id=<?php echo $book['id']; ?>" class="btn btn-outline-light border-secondary py-2">
                            <i class="bi bi-pencil me-2"></i>Edit My Listing
                        </a>
                    <?php else: ?>
                        <form method="POST" action="user/transactions.php">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <input type="hidden" name="action" value="request">
                            <input type="hidden" name="textbook_id" value="<?php echo $book['id']; ?>">
                            <button type="submit" class="btn btn-primary-gradient w-100 py-2">
                                <i class="bi bi-arrow-left-right me-2"></i>Request Exchange / Buy
                            </button>
                        </form>
                        
                        <!-- Report form -->
                        <div class="mt-3 text-center">
                            <a href="#" class="text-danger small text-decoration-none" onclick="event.preventDefault(); document.getElementById('reportForm').classList.toggle('d-none');">
                                <i class="bi bi-flag-fill me-1"></i>Report this listing
                            </a>
                            <form method="POST" action="user/transactions.php" class="d-none mt-2" id="reportForm">
                                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                <input type="hidden" name="action" value="report">
                                <input type="hidden" name="reported_user" value="<?php echo $book['user_id']; ?>">
                                <div class="input-group">
                                    <input type="text" name="reason" class="form-control form-control-custom form-control-sm" placeholder="Reason for reporting..." required>
                                    <button class="btn btn-danger btn-sm" type="submit">Report</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        exit;
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        exit;
    }
}


try {
    $db = getDBConnection();
    
    // Get unique universities & subjects for filters
    $uniQuery = $db->query("SELECT DISTINCT university FROM users WHERE university IS NOT NULL AND university != '' ORDER BY university");
    $universities = $uniQuery->fetchAll(PDO::FETCH_COLUMN);

    $subjQuery = $db->query("SELECT DISTINCT subject FROM textbooks WHERE subject IS NOT NULL AND subject != '' ORDER BY subject");
    $subjects = $subjQuery->fetchAll(PDO::FETCH_COLUMN);

    // Build query filters
    $search = trim($_GET['search'] ?? '');
    $exchange_type = trim($_GET['exchange_type'] ?? '');
    $condition = trim($_GET['condition'] ?? '');
    $university = trim($_GET['university'] ?? '');
    $subject = trim($_GET['subject'] ?? '');
    $min_price = trim($_GET['min_price'] ?? '');
    $max_price = trim($_GET['max_price'] ?? '');

    $sql = "SELECT t.*, u.name as seller_name, u.university as seller_university, u.average_rating as seller_rating,
            (SELECT image_path FROM textbook_images WHERE textbook_id = t.id LIMIT 1) as main_image
            FROM textbooks t
            JOIN users u ON t.user_id = u.id
            WHERE t.status = 'available'";
    
    $params = [];

    if ($search !== '') {
        $sql .= " AND (t.title LIKE ? OR t.author LIKE ? OR t.isbn LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($exchange_type !== '') {
        $sql .= " AND t.exchange_type = ?";
        $params[] = $exchange_type;
    }
    if ($condition !== '') {
        $sql .= " AND t.condition = ?";
        $params[] = $condition;
    }
    if ($university !== '') {
        $sql .= " AND u.university = ?";
        $params[] = $university;
    }
    if ($subject !== '') {
        $sql .= " AND t.subject = ?";
        $params[] = $subject;
    }
    if ($min_price !== '') {
        $sql .= " AND t.price >= ?";
        $params[] = floatval($min_price);
    }
    if ($max_price !== '') {
        $sql .= " AND t.price <= ?";
        $params[] = floatval($max_price);
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>
</div> <!-- Close the default container opened in header.php -->

<!-- Section 1: HERO — Full-Bleed Cinematic -->
<section id="home" class="hero-section-wrapper fade-in">
    <!-- Background Image & Overlay Layer -->
    <div class="hero-bg-image" style="background-image: url('assets/images/hero_bg.png');"></div>
    <div class="hero-overlay"></div>

    <!-- Ambient orbs -->
    <div class="hero-orb-1"></div>
    <div class="hero-orb-2"></div>

    <div class="container">
        <div class="row align-items-center g-5">

            <!-- LEFT: Copy -->
            <div class="col-lg-6 reveal">
                <span class="hero-badge mb-4">
                    <i class="bi bi-lightning-charge-fill"></i>
                    BookBridge — Student Marketplace
                </span>

                <h1 class="hero-headline display-4 fw-extrabold mb-4"
                    style="font-size: clamp(2.6rem,5vw,4.2rem); line-height:1.08; font-family:'Outfit',sans-serif; letter-spacing:-0.03em;">
                    Exchange, Buy &amp; Sell<br>
                    Textbooks with<br>
                    <span class="text-gradient">Fellow Students</span>
                </h1>

                <p class="hero-subtext mb-5" style="font-size:1.1rem; max-width:540px; line-height:1.75;">
                    Save money, reduce waste, and connect with students on campus. Buy, sell, rent or swap textbooks — trusted and verified.
                </p>

                <!-- CTA Buttons -->
                <div class="d-flex flex-wrap gap-3 mb-5">
                    <a href="#explore" class="btn btn-primary-gradient btn-lg px-5">
                        <i class="bi bi-search me-2"></i>Browse Books
                    </a>
                    <a href="<?php echo isLoggedIn() ? 'user/listings.php?action=new' : 'register.php'; ?>"
                       class="btn btn-hero-outline btn-lg px-4">
                        <i class="bi bi-plus-circle me-2"></i>List a Textbook
                    </a>
                </div>

                <!-- Stat Pills -->
                <div class="d-flex flex-wrap gap-3">
                    <div class="hero-stat-pill">
                        <div>
                            <div class="stat-val">500+</div>
                            <div class="stat-lbl">Books Listed</div>
                        </div>
                    </div>
                    <div class="hero-stat-pill">
                        <div>
                            <div class="stat-val">1,200+</div>
                            <div class="stat-lbl">Students</div>
                        </div>
                    </div>
                    <div class="hero-stat-pill">
                        <div>
                            <div class="stat-val">Free</div>
                            <div class="stat-lbl">No Platform Fee</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Floating Glass Card -->
            <div class="col-lg-6 d-none d-lg-flex justify-content-end reveal reveal-delay-2">
                <div class="hero-glass-card" style="width:360px;">
                    <!-- Card header -->
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <p style="font-size:0.78rem; opacity:0.65; margin-bottom:4px; letter-spacing:0.05em; text-transform:uppercase;">Live on Campus</p>
                            <h5 style="font-family:'Outfit',sans-serif; font-weight:700; margin:0; font-size:1.1rem;">Student Book Swap</h5>
                        </div>
                        <span class="badge rounded-pill" style="background:rgba(16,185,129,0.20); color:#6ee7b7; border:1px solid rgba(16,185,129,0.3); font-size:0.72rem; padding:0.4em 0.85em; font-weight:700;">● LIVE</span>
                    </div>

                    <!-- Mini listing cards -->
                    <div class="hero-glass-mini mb-3 d-flex align-items-center gap-3">
                        <div style="width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#818cf8); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="bi bi-book-half text-white"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Introduction to Algorithms</div>
                            <div style="font-size:0.78rem; opacity:0.65;">Cormen · CS · Good</div>
                        </div>
                        <div style="font-weight:800; font-size:0.95rem; color:#6ee7b7; flex-shrink:0;">$65</div>
                    </div>

                    <div class="hero-glass-mini mb-3 d-flex align-items-center gap-3">
                        <div style="width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#8b5cf6,#a78bfa); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="bi bi-calculator text-white"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Calculus: Early Transcendentals</div>
                            <div style="font-size:0.78rem; opacity:0.65;">Stewart · Math · New</div>
                        </div>
                        <div style="font-weight:800; font-size:0.95rem; color:#fbbf24; flex-shrink:0;">Rent</div>
                    </div>

                    <div class="hero-glass-mini d-flex align-items-center gap-3">
                        <div style="width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#06b6d4,#22d3ee); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="bi bi-atom text-white"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">University Physics</div>
                            <div style="font-size:0.78rem; opacity:0.65;">Young · Physics · Good</div>
                        </div>
                        <div style="font-weight:800; font-size:0.95rem; color:#818cf8; flex-shrink:0;">$50</div>
                    </div>

                    <!-- CTA inside card -->
                    <a href="#explore" class="btn btn-primary-gradient w-100 mt-4 py-2">
                        <i class="bi bi-arrow-right-circle me-2"></i>View All Listings
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Scroll hint -->
    <div class="hero-scroll-hint">
        <i class="bi bi-chevron-down" style="font-size:1.1rem;"></i>
        scroll
    </div>
</section>

<!-- Section 2: Features Section (Full width) -->
<section id="features" class="features-section py-5 mb-5 fade-in" style="background-color: var(--bg-card); border-top: 1px solid var(--bg-border); border-bottom: 1px solid var(--bg-border);">
    <div class="container py-4">
        <div class="row text-center mb-5 reveal">
            <div class="col-12">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Features</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">Platform Capabilities</h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">Everything you need to successfully acquire, exchange, or pass on your learning materials.</p>
            </div>
        </div>
        <div class="row g-4 reveal">
            <!-- Feature 1 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-journal-text text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Textbook Listings</h5>
                    <p class="text-secondary small mb-0">List your books for sale, rent, exchange, or donate in seconds. Upload photos and define condition variables.</p>
                </div>
            </div>
            <!-- Feature 2 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-search text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Smart Search & Filters</h5>
                    <p class="text-secondary small mb-0">Search instantly by title, author, or ISBN. Apply filters to narrow by university campus, subject, condition, or price range.</p>
                </div>
            </div>
            <!-- Feature 3 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-chat-text text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Real-Time Messaging</h5>
                    <p class="text-secondary small mb-0">Communicate directly and securely with other classmates. Agree on meeting points and inspect book details inside our chat system.</p>
                </div>
            </div>
            <!-- Feature 4 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-star text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Ratings & Reviews</h5>
                    <p class="text-secondary small mb-0">Build trust in our student network. View seller reputations and rate your trading partners after successful handovers.</p>
                </div>
            </div>
            <!-- Feature 5 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-arrow-left-right text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Book Exchange Requests</h5>
                    <p class="text-secondary small mb-0">Submit trade proposals directly to book owners. Swap books you no longer need for required textbooks.</p>
                </div>
            </div>
            <!-- Feature 6 -->
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-center mb-3 rounded-3" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.08); flex-shrink: 0;">
                        <i class="bi bi-shield-check text-gradient fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Secure Transactions</h5>
                    <p class="text-secondary small mb-0">Keep exchanges secure by trading with verified peers on campus. Review safety guides before finalizing deals.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 3: How It Works Section (Full width) -->
<section id="how-it-works" class="how-it-works py-4 mb-5 fade-in">
    <div class="container">
        <div class="row text-center mb-5 reveal">
            <div class="col-12">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Simple Steps</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">How BookBridge Works</h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">Connect and exchange textbooks in three clear steps.</p>
            </div>
        </div>
        <div class="row g-4 text-center mb-5 pb-4 reveal">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number-watermark">01</div>
                    <div class="pt-2">
                        <div class="d-inline-flex align-items-center justify-content-center mb-3 rounded-circle shadow-sm" style="width: 64px; height: 64px; background-color: rgba(99, 102, 241, 0.06); border: 1.5px solid rgba(99, 102, 241, 0.12);">
                            <i class="bi bi-plus-circle fs-3 text-gradient"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: var(--text-primary);">List Your Textbook</h5>
                        <p class="text-secondary small mb-0">Submit listing details, condition, set a price, or opt to swap or donate the book to classmates.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number-watermark">02</div>
                    <div class="pt-2">
                        <div class="d-inline-flex align-items-center justify-content-center mb-3 rounded-circle shadow-sm" style="width: 64px; height: 64px; background-color: rgba(99, 102, 241, 0.06); border: 1.5px solid rgba(99, 102, 241, 0.12);">
                            <i class="bi bi-people fs-3 text-gradient"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Connect with Students</h5>
                        <p class="text-secondary small mb-0">Receive requests and details from interested buyers or swappers, and chat locally to finalize transaction parameters.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number-watermark">03</div>
                    <div class="pt-2">
                        <div class="d-inline-flex align-items-center justify-content-center mb-3 rounded-circle shadow-sm" style="width: 64px; height: 64px; background-color: rgba(99, 102, 241, 0.06); border: 1.5px solid rgba(99, 102, 241, 0.12);">
                            <i class="bi bi-check-circle fs-3 text-gradient"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Exchange Successfully</h5>
                        <p class="text-secondary small mb-0">Meet on campus in a public safety zone, handover the book, mark as complete, and exchange feedback.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 4: Popular Categories (Full width) -->
<section id="categories" class="categories-section py-5 mb-5 fade-in" style="background-color: var(--bg-card); border-top: 1px solid var(--bg-border); border-bottom: 1px solid var(--bg-border);">
    <div class="container py-4">
        <div class="row text-center mb-5 reveal">
            <div class="col-12">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Browse</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">Popular Categories</h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">Select a subject category to discover textbooks listed by senior classes.</p>
            </div>
        </div>
        <div class="row g-4 reveal">
            <!-- Cat 1 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Computer+Science#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-laptop text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Comp Science</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">24 Books</span>
                    </div>
                </a>
            </div>
            <!-- Cat 2 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Engineering#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-cpu text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Engineering</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">18 Books</span>
                    </div>
                </a>
            </div>
            <!-- Cat 3 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Mathematics#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-calculator text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Mathematics</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">14 Books</span>
                    </div>
                </a>
            </div>
            <!-- Cat 4 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Medical#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-heart-pulse text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Medical</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">20 Books</span>
                    </div>
                </a>
            </div>
            <!-- Cat 5 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Business+Studies#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-briefcase text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Business</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">16 Books</span>
                    </div>
                </a>
            </div>
            <!-- Cat 6 -->
            <div class="col-6 col-md-4 col-lg-2">
                <a href="index.php?subject=Literature#explore" class="text-decoration-none">
                    <div class="card card-custom p-3 text-center bg-input border-0">
                        <i class="bi bi-journal-richtext text-gradient fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 text-truncate" style="color: var(--text-primary);">Literature</h6>
                        <span class="badge bg-white border border-border text-secondary rounded-pill small">12 Books</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Section 5: Featured Books & Exploration Grid (Centered container) -->
<section class="explore-section py-4 mb-5 fade-in" id="explore">
    <div class="container">
        <div class="row">
            <!-- Filter Sidebar -->
            <div class="col-lg-3 mb-4 reveal">
                <div class="card card-custom sticky-top" style="top: 90px; z-index: 10;">
                    <div class="card-header-custom">
                        <h4 class="mb-0 text-gradient"><i class="bi bi-funnel me-2"></i>Filters</h4>
                    </div>
                    <div class="card-body-custom">
                        <form method="GET" action="index.php">
                            <!-- Search Input -->
                            <div class="mb-3">
                                <label for="search" class="form-label text-secondary small fw-bold uppercase">Keyword</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-input border-border text-secondary"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control form-control-custom" id="search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Title, Author, ISBN...">
                                </div>
                            </div>

                            <!-- Type filter -->
                            <div class="mb-3">
                                <label for="exchange_type" class="form-label text-secondary small fw-bold">Transaction Type</label>
                                <select class="form-select form-select-custom" id="exchange_type" name="exchange_type">
                                    <option value="">All Types</option>
                                    <option value="sell" <?php echo $exchange_type === 'sell' ? 'selected' : ''; ?>>Buy/Sell</option>
                                    <option value="exchange" <?php echo $exchange_type === 'exchange' ? 'selected' : ''; ?>>Exchange</option>
                                    <option value="rent" <?php echo $exchange_type === 'rent' ? 'selected' : ''; ?>>Rent</option>
                                    <option value="donate" <?php echo $exchange_type === 'donate' ? 'selected' : ''; ?>>Donate</option>
                                </select>
                            </div>

                            <!-- University filter -->
                            <div class="mb-3">
                                <label for="university" class="form-label text-secondary small fw-bold">College / University</label>
                                <select class="form-select form-select-custom" id="university" name="university">
                                    <option value="">All Universities</option>
                                    <?php foreach ($universities as $uni): ?>
                                        <option value="<?php echo sanitize($uni); ?>" <?php echo $university === $uni ? 'selected' : ''; ?>><?php echo sanitize($uni); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Subject filter -->
                            <div class="mb-3">
                                <label for="subject" class="form-label text-secondary small fw-bold">Subject / Department</label>
                                <select class="form-select form-select-custom" id="subject" name="subject">
                                    <option value="">All Subjects</option>
                                    <?php foreach ($subjects as $sub): ?>
                                        <option value="<?php echo sanitize($sub); ?>" <?php echo $subject === $sub ? 'selected' : ''; ?>><?php echo sanitize($sub); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Condition filter -->
                            <div class="mb-3">
                                <label for="condition" class="form-label text-secondary small fw-bold">Book Condition</label>
                                <select class="form-select form-select-custom" id="condition" name="condition">
                                    <option value="">All Conditions</option>
                                    <option value="New" <?php echo $condition === 'New' ? 'selected' : ''; ?>>New</option>
                                    <option value="Good" <?php echo $condition === 'Good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="Fair" <?php echo $condition === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                </select>
                            </div>

                            <!-- Price range -->
                            <div class="mb-4">
                                <label class="form-label text-secondary small fw-bold">Price Range ($)</label>
                                <div class="d-flex gap-2">
                                    <input type="number" class="form-control form-control-custom text-center" name="min_price" value="<?php echo sanitize($min_price); ?>" placeholder="Min" min="0">
                                    <span class="align-self-center text-secondary">-</span>
                                    <input type="number" class="form-control form-control-custom text-center" name="max_price" value="<?php echo sanitize($max_price); ?>" placeholder="Max" min="0">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary-gradient py-2">Apply Filters</button>
                                <a href="index.php" class="text-center text-secondary small text-decoration-none mt-2 hover-accent-color">Reset All Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Listings Grid -->
            <div class="col-lg-9 reveal reveal-delay-1">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0 text-gradient">Featured Textbooks</h3>
                    <span class="text-secondary"><?php echo count($listings); ?> books found</span>
                </div>

                <?php if (empty($listings)): ?>
                    <div class="card card-custom text-center p-5 my-5">
                        <i class="bi bi-journal-x text-secondary" style="font-size: 4rem;"></i>
                        <h4 class="mt-3" style="color: var(--text-primary);">No Books Found</h4>
                        <p class="text-secondary">No active textbook listings match your search criteria. Try removing some filters or change your query!</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($listings as $book): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card card-custom d-flex flex-column">
                                    <div class="position-relative">
                                        <div class="card-img-wrapper" style="height: 220px; overflow: hidden; position: relative;">
                                            <?php if ($book['main_image']): ?>
                                                <img src="<?php echo sanitize($book['main_image']); ?>" class="card-img-top w-100 h-100" style="object-fit: cover;" alt="<?php echo sanitize($book['title']); ?>">
                                            <?php else: ?>
                                                <div class="bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-secondary h-100 w-100">
                                                    <i class="bi bi-book-half" style="font-size: 4rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Type badge -->
                                        <span class="position-absolute top-0 end-0 m-3 badge badge-custom badge-<?php echo strtolower($book['exchange_type']); ?>">
                                            <?php echo strtoupper($book['exchange_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="card-body-custom d-flex flex-column flex-grow-1 p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title text-truncate mb-0" style="max-width: 75%; color: var(--text-primary);" title="<?php echo sanitize($book['title']); ?>">
                                                <?php echo sanitize($book['title']); ?>
                                            </h5>
                                            <div class="text-end">
                                                <?php if ($book['exchange_type'] == 'sell'): ?>
                                                    <span class="text-primary font-weight-bold fs-5">$<?php echo number_format($book['price'], 2); ?></span>
                                                <?php elseif ($book['exchange_type'] == 'rent'): ?>
                                                    <span class="text-primary font-weight-bold fs-5">$<?php echo number_format($book['price'], 2); ?> <small class="text-secondary small">/sem</small></span>
                                                <?php else: ?>
                                                    <span class="badge badge-custom badge-<?php echo strtolower($book['exchange_type']); ?>"><?php echo ucfirst($book['exchange_type']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <p class="text-secondary small mb-2 text-truncate">By <?php echo sanitize($book['author']); ?></p>

                                        <div class="d-flex gap-2 mb-3">
                                            <span class="badge badge-custom badge-<?php echo strtolower($book['condition']); ?>"><?php echo sanitize($book['condition']); ?></span>
                                            <?php if ($book['edition']): ?>
                                                <span class="badge bg-input border border-border text-secondary"><?php echo sanitize($book['edition']); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="border-top border-border pt-2 mt-auto d-flex align-items-center justify-content-between small">
                                            <div>
                                                <div class="text-secondary text-truncate" style="max-width: 140px;" title="<?php echo sanitize($book['seller_university']); ?>">
                                                    <i class="bi bi-geo-alt me-1 text-gradient"></i><?php echo sanitize($book['seller_university']); ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="text-secondary">Owner: <?php echo sanitize($book['seller_name']); ?></span>
                                                <div class="star-rating small">
                                                    <i class="bi bi-star-fill"></i>
                                                    <span class="text-secondary"><?php echo number_format($book['seller_rating'], 1); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <button class="btn btn-view-details btn-sm w-100 mt-3" 
                                                onclick="showBookDetails(<?php echo $book['id']; ?>)">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Section 6: Statistics Section (Full width) -->
<section class="statistics-section py-5 mb-5 fade-in" style="background-color: var(--bg-card); border-top: 1px solid var(--bg-border); border-bottom: 1px solid var(--bg-border);">
    <div class="container py-4">
        <div class="row g-4 reveal">
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center">
                    <div class="stat-number">5,000+</div>
                    <div class="text-secondary small fw-semibold">Active Students</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center">
                    <div class="stat-number">12,000+</div>
                    <div class="text-secondary small fw-semibold">Books Listed</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center">
                    <div class="stat-number">8,500+</div>
                    <div class="text-secondary small fw-semibold">Successful Trades</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card text-center">
                    <div class="stat-number">4.9/5</div>
                    <div class="text-secondary small fw-semibold">User Rating</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 7: Testimonials (Full width) -->
<section id="testimonials" class="testimonials-section py-5 mb-5 fade-in">
    <div class="container">
        <div class="row text-center mb-5 reveal">
            <div class="col-12">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Reviews</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">What Students Say</h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">Hear from university peers who are cutting educational costs.</p>
            </div>
        </div>
        <div class="row g-4 reveal">
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <p class="text-secondary small mb-3 italic">"BookBridge helped me save hundreds on semester textbooks. I exchanged my Calculus manual with a junior who had the Compilers book I needed. Excellent process!"</p>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width: 44px; height: 44px; font-weight: 600; background: var(--accent-gradient);">
                            ST
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 small" style="color: var(--text-primary);">Sarah T.</h6>
                            <span class="text-secondary small" style="font-size: 0.75rem;">Computer Science, Harvard</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom p-4">
                    <p class="text-secondary small mb-3 italic">"I was able to donate my old physics textbook to a freshman who couldn't afford a new copy. It feels great to help out classmate communities."</p>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width: 44px; height: 44px; font-weight: 600; background: var(--accent-gradient);">
                            DK
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 small" style="color: var(--text-primary);">David K.</h6>
                            <span class="text-secondary small" style="font-size: 0.75rem;">Physics, MIT</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 d-md-none d-lg-block">
                <div class="card card-custom p-4">
                    <p class="text-secondary small mb-3 italic">"As an engineering student, book costs are ridiculous. BookBridge let me rent textbook bundles for a fraction of the cost from campus seniors."</p>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width: 44px; height: 44px; font-weight: 600; background: var(--accent-gradient);">
                            BJ
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 small" style="color: var(--text-primary);">Bob J.</h6>
                            <span class="text-secondary small" style="font-size: 0.75rem;">Mechanical Eng, Harvard</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 8: Why Choose BookBridge (Full width) -->
<section class="why-choose-section py-5 mb-5 fade-in" style="background-color: var(--bg-card); border-top: 1px solid var(--bg-border); border-bottom: 1px solid var(--bg-border);">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-lg-5 mb-4 mb-lg-0 reveal">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Benefits</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">Why Choose BookBridge?</h2>
                <p class="text-secondary mb-4" style="line-height: 1.6;">We bridge the gap between academic affordability and resource preservation through student trust.</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary-gradient px-4 py-2">Join Campus Community</a>
                <?php else: ?>
                    <a href="user/listings.php?action=new" class="btn btn-primary-gradient px-4 py-2">List Textbook Now</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-7 reveal reveal-delay-1">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <div class="card card-custom p-4 bg-input border-0">
                            <i class="bi bi-wallet2 text-gradient fs-2 mb-2"></i>
                            <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Affordable Learning</h5>
                            <p class="text-secondary small mb-0">Skip commercial bookstore markups. Buy, sell, or rent directly from seniors at fractional costs.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-custom p-4 bg-input border-0">
                            <i class="bi bi-globe-americas text-gradient fs-2 mb-2"></i>
                            <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Eco-Friendly Book Reuse</h5>
                            <p class="text-secondary small mb-0">Reduce print pollution and paper waste. Reusing coursebooks is a vote for sustainability.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-custom p-4 bg-input border-0">
                            <i class="bi bi-shield-check text-gradient fs-2 mb-2"></i>
                            <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Trusted Student Community</h5>
                            <p class="text-secondary small mb-0">Transactions are limited to your college community. Profiles and student ratings keep trades secure.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-custom p-4 bg-input border-0">
                            <i class="bi bi-chat-text text-gradient fs-2 mb-2"></i>
                            <h5 class="fw-bold mb-2" style="color: var(--text-primary);">Easy Communication</h5>
                            <p class="text-secondary small mb-0">Discuss meeting spots, textbook condition, and handle details inside our instant messenger.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 8: Contact Section (Full width) -->
<section id="contact" class="contact-section py-5 mb-5 fade-in" style="background-color: var(--bg-card); border-top: 1px solid var(--bg-border); border-bottom: 1px solid var(--bg-border);">
    <div class="container py-4">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 reveal">
                <span class="text-uppercase text-gradient small fw-bold tracking-wider">Get in Touch</span>
                <h2 class="display-6 fw-bold mt-1 mb-3" style="color: var(--text-primary);">Questions about textbook exchange?</h2>
                <p class="text-secondary mb-4" style="line-height: 1.75;">Reach out to our student support team, request help with listing, or learn how BookBridge can power your campus marketplace.</p>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <a href="mailto:support@bookbridge.example" class="btn btn-primary-gradient px-4 py-2">Email Support</a>
                    <a href="register.php" class="btn btn-outline-secondary px-4 py-2" style="border-radius: 12px;">Create Account</a>
                </div>
            </div>
            <div class="col-lg-6 reveal reveal-delay-1">
                <div class="card card-custom border-0 p-4 bg-input">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="color: var(--text-primary);">Office Hours</h5>
                            <p class="small text-secondary mb-0">Mon - Fri · 8:00 AM - 8:00 PM</p>
                        </div>
                        <i class="bi bi-question-circle text-gradient fs-2"></i>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="bg-card p-3 rounded-4 border border-border h-100">
                                <h6 class="fw-bold mb-1">Location</h6>
                                <p class="small text-secondary mb-0">Campus Network · Online First</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-card p-3 rounded-4 border border-border h-100">
                                <h6 class="fw-bold mb-1">Phone</h6>
                                <p class="small text-secondary mb-0">+1 (555) 123-4567</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-card p-3 rounded-4 border border-border h-100">
                                <h6 class="fw-bold mb-1">Email</h6>
                                <p class="small text-secondary mb-0">support@bookbridge.example</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-card p-3 rounded-4 border border-border h-100">
                                <h6 class="fw-bold mb-1">Quick Links</h6>
                                <p class="small text-secondary mb-0">FAQ · Privacy · Terms</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 9: Call-To-Action Section (Full width) -->
<section class="cta-section py-5 mb-5 fade-in">
    <div class="container py-4 text-center reveal">
        <div class="card card-custom p-5 bg-gradient border-0 shadow-lg" style="background: var(--accent-gradient) !important; color: white !important;">
            <div class="py-3">
                <h2 class="display-5 fw-extrabold mb-3 text-white">Ready to Give Your Old Books a New Home?</h2>
                <p class="lead mb-4 mx-auto text-white-50" style="max-width: 600px; font-size: 1.15rem;">List your textbooks today, cut semester costs, and help another classmate learn sustainably.</p>
                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <?php if (isLoggedIn()): ?>
                        <a href="user/listings.php?action=new" class="btn btn-light btn-lg px-4 py-2 fw-bold text-primary rounded-pill border-0 shadow" style="color: var(--accent-primary) !important;">List a Book</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-light btn-lg px-4 py-2 fw-bold text-primary rounded-pill border-0 shadow" style="color: var(--accent-primary) !important;">Join Now</a>
                    <?php endif; ?>
                    <a href="#explore" class="btn btn-outline-light btn-lg px-4 py-2 fw-bold rounded-pill" style="border-width: 2px;">Explore Library</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Shared Book Details Modal -->
<div class="modal fade" id="bookDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-card border-border text-light shadow-lg">
            <div class="modal-header card-header-custom border-border">
                <h5 class="modal-title" id="modal-book-title" style="color: var(--text-primary);">Book Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modal-book-content">
                <!-- Loaded dynamically by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript helpers to fetch book detail and inject in modal -->
<script>
function showBookDetails(bookId) {
    const modal = new bootstrap.Modal(document.getElementById('bookDetailsModal'));
    const contentBox = document.getElementById('modal-book-content');
    
    // Clear & show loading
    contentBox.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    modal.show();

    // Fetch details
    fetch(`index.php?get_details=1&book_id=${bookId}`)
        .then(response => response.text())
        .then(html => {
            contentBox.innerHTML = html;
        })
        .catch(err => {
            contentBox.innerHTML = `<div class="alert alert-danger">Error loading book details: ${err}</div>`;
        });
}

function selectGalleryThumb(imgUrl, element) {
    document.getElementById('modal-main-gallery').src = imgUrl;
    document.querySelectorAll('.gallery-thumb').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
}

// Scroll Reveal Observer
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('active');
            revealObserver.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.reveal').forEach(el => {
        revealObserver.observe(el);
    });
});
</script>

<div class="container"> <!-- Reopen the container for footer.php to close -->
<?php
require_once __DIR__ . '/includes/footer.php';
?>
