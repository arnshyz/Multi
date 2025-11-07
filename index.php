<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKAY.IO - Cinematic Videos Instantly</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="light-mode">
    <div class="background">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <header>
        <div class="navbar glass-card">
            <div class="logo">
                <img src="logo.png" alt="AKAY.IO Logo" class="logo-icon">
                AKAY.IO
            </div>
            <nav class="desktop-menu">
                <a href="#">Product</a>
                <a href="#">Use Cases</a>
                <a href="#">Resource</a>
                <a href="#">Pricing</a>
            </nav>
            <div class="nav-actions">
                <button id="dark-mode-toggle" class="icon-btn" type="button" aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="#" class="btn-login"><i class="fas fa-arrow-right"></i> Login</a>
                <a href="#" class="btn-signup">Sign Up</a>
                <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Toggle mobile menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-dropdown glass-card" id="mobile-menu">
        <a href="#">Product</a>
        <a href="#">Use Cases</a>
        <a href="#">Resource</a>
        <a href="#">Pricing</a>
        <div class="mobile-actions">
            <a href="#" class="btn-login-mobile"><i class="fas fa-arrow-right"></i> Login</a>
            <a href="#" class="btn-signup-mobile"><i class="fas fa-user-plus"></i> Sign Up</a>
        </div>
    </div>

    <main>
        <section class="hero-section">
            <div class="hero-text">
                <h1>
                    <span class="gradient-text">Cinematic Videos</span>
                    Instantly
                </h1>
                <p>Powered by <strong>Veo 3.1 &amp; Sora 2</strong> — The world's most advanced AI video generation models</p>
                <p class="subtitle-mobile">Generate studio-quality videos without any technical skills.</p>
                <div class="cta-area">
                    <a href="#" class="cta-button">
                        <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                        <span class="text-part">Start Testing Free</span>
                    </a>
                    <div class="active-users">1000+ Active Users</div>
                </div>
            </div>
            <div class="stats-panel">
                <div class="stat-card glass-card stat-big">
                    <p>10K+</p>
                    <span>Videos Created by users</span>
                </div>
                <div class="stat-card-row">
                    <div class="stat-card glass-card stat-small">
                        <p>30s</p>
                        <span>Avg. Time</span>
                    </div>
                    <div class="stat-card glass-card stat-small">
                        <p>98%</p>
                        <span>Success Rate</span>
                    </div>
                </div>
                <div class="stat-card glass-card feature-badge">
                    <i class="fas fa-rocket"></i> Real-time processing
                </div>
            </div>
        </section>

        <section class="product-features" id="product">
            <div class="product-header">
                <h2 class="product-title">The AI Video Ad Maker</h2>
                <p class="product-subtitle">built for instant performance</p>
                <p class="product-description">Turn any product page into a winning video ad—created, tested, and optimized in seconds.</p>
            </div>

            <div class="feature-card glass-card">
                <div class="feature-image">
                    <span class="image-badge">UGC Affiliate</span>
                    <img src="https://via.placeholder.com/250x350/9966FF/FFFFFF?text=Product+1" alt="Instant UGC Video Generation">
                </div>
                <div class="feature-content">
                    <span class="feature-tag">AI-POWERED</span>
                    <h3>Instant UGC Video Generation</h3>
                    <p>Transform any product into authentic user-generated content videos in minutes. Our AI discovers music, engaging videos that look like they come from real customers.</p>
                    <a href="#" class="btn-try-now">
                        <i class="fas fa-arrow-right"></i> Try it now
                    </a>
                </div>
            </div>

            <div class="feature-card glass-card reverse-layout">
                <div class="feature-image">
                    <span class="image-badge">Product Demo</span>
                    <img src="https://via.placeholder.com/250x350/FF66B2/FFFFFF?text=Product+2" alt="Real-Time Ad Optimization">
                </div>
                <div class="feature-content">
                    <span class="feature-tag">LIGHTNING FAST</span>
                    <h3>Real-Time Ad Optimization</h3>
                    <p>Automatically test multiple variations and optimize for maximum conversion. Our AI analyzes performance and adapts your ads in real-time for best results.</p>
                    <a href="#" class="btn-try-now">
                        <i class="fas fa-arrow-right"></i> Try it now
                    </a>
                </div>
            </div>

            <div class="feature-card glass-card">
                <div class="feature-image">
                    <span class="image-badge">Analytics View</span>
                    <img src="https://via.placeholder.com/250x350/66FFFF/FFFFFF?text=Product+3" alt="Data-Driven Insights">
                </div>
                <div class="feature-content">
                    <span class="feature-tag">PERFORMANCE</span>
                    <h3>Data-Driven Insights</h3>
                    <p>Get detailed analytics on what works and what doesn't. Track engagement, conversions, and ROI across all your video ads with actionable insights.</p>
                    <a href="#" class="btn-try-now">
                        <i class="fas fa-arrow-right"></i> Try it now
                    </a>
                </div>
            </div>

            <div class="feature-card glass-card reverse-layout">
                <div class="feature-image">
                    <span class="image-badge">Target Audience</span>
                    <img src="https://via.placeholder.com/250x350/FFD700/FFFFFF?text=Product+4" alt="Audience Precision">
                </div>
                <div class="feature-content">
                    <span class="feature-tag">SMART TARGETING</span>
                    <h3>Audience Precision</h3>
                    <p>Reach the right audience at the right time. Our AI automatically identifies and targets your ideal customers based on behavior and engagement patterns.</p>
                    <a href="#" class="btn-try-now">
                        <i class="fas fa-arrow-right"></i> Try it now
                    </a>
                </div>
            </div>
        </section>

        <section class="use-cases" id="use-cases">
            <div class="use-cases-header">
                <h2>How AKAY.IO Works</h2>
                <p>From inspiration to optimization—your complete workflow for creating winning video ads.</p>
            </div>

            <div class="workflow-grid">
                <div class="workflow-card glass-card">
                    <div class="card-icon"><i class="fas fa-search"></i></div>
                    <div class="card-number">01</div>
                    <h4>Get Inspired</h4>
                    <p class="card-subtitle">Find what works.</p>
                    <p>Explore top-performing ads across your category or competition by hook, selling point, and visuals.</p>
                    <div class="underline-blue"></div>
                </div>

                <div class="workflow-card glass-card">
                    <div class="card-icon"><i class="fas fa-rocket"></i></div>
                    <div class="card-number">02</div>
                    <h4>Create Winning Ads</h4>
                    <p class="card-subtitle">From link to launch, instantly.</p>
                    <p>Turn a product URL or static asset into a scroll-stopping video ads. Customize with music, emotion, or voiceover.</p>
                    <div class="underline-red"></div>
                </div>

                <div class="workflow-card glass-card">
                    <div class="card-icon"><i class="fas fa-rocket"></i></div>
                    <div class="card-number">03</div>
                    <h4>Launch and Test</h4>
                    <p class="card-subtitle">Test everything with rigor.</p>
                    <p>Run creative with scores video variants. Discover winners by key format, hook, or audience—automatically.</p>
                    <div class="underline-orange"></div>
                </div>

                <div class="workflow-card glass-card">
                    <div class="card-icon"><i class="fas fa-rocket"></i></div>
                    <div class="card-number">04</div>
                    <h4>Learn and Optimize</h4>
                    <p class="card-subtitle">Know what’s working—and why.</p>
                    <p>Get real-time insights on ROAS, CPA, and other key metrics. Spot fatigue and rapidly improve campaigns.</p>
                    <div class="underline-cyan"></div>
                </div>
            </div>

            <div class="cases-cta">
                <p>Ready to transform your ad creation process?</p>
                <a href="#" class="cta-button-small">
                    <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                    <span class="text-part">Get Started Free</span>
                </a>
            </div>

            <div class="real-results">
                <div class="results-text">
                    <h3>Real Results</h3>
                    <small>Based on internal benchmarks &amp; internal data</small>
                </div>
                <div class="stats-data">
                    <div class="stat-item">
                        <p class="stat-number stat-blue">2.7x</p>
                        <small>More leads vs. static image ads</small>
                    </div>
                    <div class="stat-item">
                        <p class="stat-number stat-red">1.7x</p>
                        <small>Higher ROI</small>
                    </div>
                    <div class="stat-item">
                        <p class="stat-number stat-green">90%</p>
                        <small>Lower production cost</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="pricing-section" id="pricing">
            <div class="pricing-header">
                <h2>Pick a plan</h2>
                <p>or get started for free</p>
                <p class="pricing-subtitle">Plans for creators, marketers, and agencies of all sizes.</p>
            </div>

            <div class="pricing-grid">
                <div class="plan-card glass-card">
                    <h3>Free Plan</h3>
                    <p class="plan-tag">Start testing on AKAY.IO at no cost</p>
                    <div class="price-container">
                        <span class="currency">Rp</span>
                        <span class="price-value" data-final-price="0">0</span>
                    </div>
                    <p class="price-lifetime">Forever</p>
                    <div class="key-features">
                        <h4>Key Features:</h4>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Browse dashboard only</li>
                            <li><i class="fas fa-check-circle"></i> View features &amp; pricing</li>
                            <li class="disabled-feature"><i class="fas fa-circle-minus"></i> No generator access</li>
                        </ul>
                    </div>
                    <a href="#" class="btn-plan-select">
                        <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                        <span class="text-part">Get Started</span>
                    </a>
                </div>

                <div class="plan-card glass-card most-popular">
                    <div class="popular-badge">★ MOST POPULAR</div>
                    <h3>Pro Plan</h3>
                    <p class="plan-tag">For creators, marketers, and agencies</p>
                    <div class="price-container">
                        <span class="currency">Rp</span>
                        <span class="price-value" data-final-price="399000">399.000</span>
                    </div>
                    <p class="price-lifetime">/ lifetime</p>
                    <div class="price-strikeout">
                        <p>Rp <del>500.000</del></p>
                        <span class="save-badge">Save 20%</span>
                    </div>
                    <div class="key-features">
                        <h4>Key Features:</h4>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Veo 3 &amp; Veo 2 - UNLIMITED</li>
                            <li><i class="fas fa-check-circle"></i> Voice &amp; Image Generator - UNLIMITED</li>
                            <li><i class="fas fa-check-circle"></i> Face Swap - UNLIMITED</li>
                            <li><i class="fas fa-check-circle"></i> Grup Tutorial Premium</li>
                            <li><i class="fas fa-check-circle"></i> + 7 more features</li>
                        </ul>
                    </div>
                    <a href="#" class="btn-plan-select btn-primary-plan">
                        <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                        <span class="text-part">Get Full Access</span>
                    </a>
                </div>

                <div class="plan-card glass-card">
                    <h3>Master Plan</h3>
                    <p class="plan-tag">Ultimate power for professionals</p>
                    <div class="price-container">
                        <span class="currency">Rp</span>
                        <span class="price-value" data-final-price="699000">699.000</span>
                    </div>
                    <p class="price-lifetime">/ lifetime</p>
                    <div class="key-features">
                        <h4>Key Features:</h4>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Semua Fitur Pro Plan</li>
                            <li><i class="fas fa-check-circle"></i> Kling, Seedance, Nano Banana Unlimited - Selamanya</li>
                            <li><i class="fas fa-check-circle"></i> Bot Telegram All-in-One</li>
                            <li><i class="fas fa-check-circle"></i> Kecepatan Tinggi &amp; Stabil</li>
                            <li><i class="fas fa-check-circle"></i> No Garansi (Trick &amp; Celah)</li>
                            <li><i class="fas fa-check-circle"></i> + 9 more features</li>
                        </ul>
                    </div>
                    <a href="#" class="btn-plan-select">
                        <span class="icon-part"><i class="fas fa-arrow-right"></i></span>
                        <span class="text-part">Get Lifetime Access</span>
                    </a>
                </div>
            </div>
            <div class="contact-footer">
                <p>Need a custom solution? <a href="#">Contact us</a> for enterprise pricing</p>
            </div>
        </section>

        <section class="review-section" id="reviews">
            <div class="review-header">
                <h2>Loved by Marketers</h2>
                <p>who create winning ads</p>
                <p class="review-subtitle">Join thousands of marketers who are crushing their ad campaigns with AI-powered video creation.</p>
            </div>

            <div class="reviews-grid">
                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"Yo, the Veo 3.1 unlimited feature is literally insane. I'm pumping out ads like crazy and not worrying about credits running out. Game changer for my workflow, honestly."</p>
                    <div class="review-tag">Unlimited Veo 3.1</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">S</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">Sarah Jombang</p>
                            <small>E-commerce Manager at FashionHub</small>
                        </div>
                    </div>
                </div>

                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"Ducks, Akaygen for UGC content is fire! The videos look so authentic, you'd swear they're from real customers. My conversion rates went through the roof. Can't believe how good this is."</p>
                    <div class="review-tag">AkayGen UGC Master</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">M</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">JND Store</p>
                            <small>Content Creator at TechGadgets Pro</small>
                        </div>
                    </div>
                </div>

                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"Okay so Sora 2 at Rp 500 is absolutely wild. Other platforms charging Rp 15,000 for the same thing. Like, are you kidding me? This pricing is unreal. Already saved thousands this month."</p>
                    <div class="review-tag">Sora 2 - Rp 500 only</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">E</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">Bagus</p>
                            <small>Founder &amp; CEO at BeautyBox</small>
                        </div>
                    </div>
                </div>

                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"The combo of unlimited Veo + cheap Sora 2 is just ridiculous value. I'm testing so many variations now. My clients are stoked with the results. This platform is lowkey a cheat code."</p>
                    <div class="review-tag">Testing 100+ variations</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">D</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">Erwanda Ade</p>
                            <small>Performance Marketer at GrowthLabs</small>
                        </div>
                    </div>
                </div>

                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"NexaGen UGC videos are chef's kiss. They look so real that my followers think I hired actual creators. And the fact that it's unlimited? Bruh, I'm never going back to manual editing."</p>
                    <div class="review-tag">100% organic look</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">J</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">Dimas Bagus</p>
                            <small>Social Media Manager at FitnessPro</small>
                        </div>
                    </div>
                </div>

                <div class="review-card glass-card">
                    <div class="quote-icon">”</div>
                    <div class="rating">★★★★★</div>
                    <p class="review-text">"Compared AKAY.IO with 3 competitors and honestly, no contest. Veo 3.1 unlimited + Sora 2 at insane prices + NexaGen quality? Canceled everything else. This is THE tool now."</p>
                    <div class="review-tag">Switched from 3 platforms</div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">A</div>
                        <div class="reviewer-details">
                            <p class="reviewer-name">Wibi Santoso</p>
                            <small>Head of Growth at DigitalNomad</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="review-stats">
                <div class="stat-item">
                    <p class="stat-number">10K+</p>
                    <small>Active Users</small>
                </div>
                <div class="stat-item">
                    <p class="stat-number">2M+</p>
                    <small>Videos Generated</small>
                </div>
                <div class="stat-item">
                    <p class="stat-number">98%</p>
                    <small>Satisfaction Rate</small>
                </div>
                <div class="stat-item">
                    <p class="stat-number">4.9/5</p>
                    <small>Average Rating</small>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-col footer-info">
                <div class="footer-logo">
                    <img src="logo.png" alt="Nexabot Logo" class="logo-icon">
                    AKAY NUSANTARA
                </div>
                <p class="footer-description">AI-powered video ads that convert. Create winning ads in seconds with the power of artificial intelligence.</p>
                <div class="social-icons">
                    <a href="#" class="icon-circle"><i class="fab fa-tiktok"></i></a>
                    <a href="#" class="icon-circle"><i class="fab fa-telegram-plane"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h3>PRODUCT</h3>
                <ul>
                    <li><a href="#">Get Started</a></li>
                    <li><a href="#">Pricing</a></li>
                    <li><a href="#">Features</a></li>
                    <li><a href="#">How It Works</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>RESOURCES</h3>
                <ul>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Tutorials</a></li>
                    <li><a href="#">Case Studies</a></li>
                    <li><a href="#">Help Center</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>COMPANY</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 AKAY NUSANTARA. All rights reserved.</p>
            <div class="bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#"><i class="fas fa-envelope"></i> Contact</a>
            </div>
        </div>
    </footer>

    <div id="login-modal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="login-title">
        <div class="modal-content glass-card">
            <button class="close-btn" type="button" aria-label="Close login modal">&times;</button>
            <h2 id="login-title">Login ke AKAY.IO</h2>
            <form>
                <input type="email" placeholder="Email" required>
                <input type="password" placeholder="Password" required>
                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>
    </div>

    <div id="signup-modal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="signup-title">
        <div class="modal-content glass-card">
            <button class="close-btn" type="button" aria-label="Close sign up modal">&times;</button>
            <h2 id="signup-title">Daftar Akun Baru</h2>
            <form>
                <input type="text" placeholder="Nama Pengguna" required>
                <input type="email" placeholder="Email" required>
                <input type="password" placeholder="Password" required>
                <button type="submit" class="btn-primary">Sign Up</button>
            </form>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const body = document.body;

        const loginModal = document.getElementById('login-modal');
        const signupModal = document.getElementById('signup-modal');
        const loginBtn = document.querySelector('.btn-login');
        const signupBtn = document.querySelector('.btn-signup');
        const loginMobileBtn = document.querySelector('.btn-login-mobile');
        const signupMobileBtn = document.querySelector('.btn-signup-mobile');

        function toggleMobileMenu() {
            if (!mobileMenu) return;
            mobileMenu.classList.toggle('open');
            const icon = menuToggle?.querySelector('i');
            if (icon) {
                if (mobileMenu.classList.contains('open')) {
                    icon.classList.replace('fa-bars', 'fa-times');
                } else {
                    icon.classList.replace('fa-times', 'fa-bars');
                }
            }
        }

        function toggleDarkMode() {
            body.classList.toggle('dark-mode');
            body.classList.toggle('light-mode');
            const icon = darkModeToggle?.querySelector('i');
            if (!icon) return;
            if (body.classList.contains('dark-mode')) {
                icon.classList.replace('fa-moon', 'fa-sun');
            } else {
                icon.classList.replace('fa-sun', 'fa-moon');
            }
        }

        function openModal(modalElement) {
            if (!modalElement) return;
            modalElement.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalElement) {
            if (!modalElement) return;
            modalElement.classList.remove('is-open');
            document.body.style.overflow = 'auto';
        }

        function bindModalTriggers(trigger, modal) {
            if (!trigger || !modal) return;
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                if (mobileMenu && mobileMenu.classList.contains('open')) {
                    mobileMenu.classList.remove('open');
                    const icon = menuToggle?.querySelector('i');
                    icon?.classList.replace('fa-times', 'fa-bars');
                }
                openModal(modal);
            });
        }

        const closeButtons = document.querySelectorAll('.modal .close-btn');
        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = button.closest('.modal');
                closeModal(modal);
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target === loginModal) {
                closeModal(loginModal);
            }
            if (event.target === signupModal) {
                closeModal(signupModal);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal(loginModal);
                closeModal(signupModal);
            }
        });

        function animatePrice(element, finalPrice, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const easedProgress = 1 - Math.pow(1 - progress, 3);
                const currentPrice = Math.floor(easedProgress * finalPrice);
                const formattedPrice = currentPrice.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                element.textContent = formattedPrice;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (menuToggle) {
                menuToggle.addEventListener('click', toggleMobileMenu);
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', toggleDarkMode);
            }
            bindModalTriggers(loginBtn, loginModal);
            bindModalTriggers(signupBtn, signupModal);
            bindModalTriggers(loginMobileBtn, loginModal);
            bindModalTriggers(signupMobileBtn, signupModal);

            const priceElements = document.querySelectorAll('.price-value');
            priceElements.forEach((element) => {
                const finalPrice = parseInt(element.getAttribute('data-final-price'), 10) || 0;
                element.textContent = '0';
                if (finalPrice > 0) {
                    animatePrice(element, finalPrice, 1500);
                }
            });
        });
    </script>
</body>
</html>
