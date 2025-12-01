<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Maker - Smart Expense Sharing App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- Add AOS library for scroll animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        /* Enhanced Root Variables */
        :root {
            --primary-blue: #2563EB;
            --primary-gradient: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            --secondary-gradient: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            --success-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --card-shadow: 0 8px 24px rgba(37, 99, 235, 0.1);
            --hover-transform: translateY(-5px);
            --transition-speed: 0.3s;
        }

        /* Enhanced Feature Cards */
        .feature-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all var(--transition-speed) ease;
            background: white;
            box-shadow: var(--card-shadow);
        }

        .feature-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.15);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-gradient);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: white;
            transition: all var(--transition-speed) ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* Enhanced Hero Section */
        .hero-section {
            position: relative;
            overflow: hidden;
            padding: 8rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2320c997' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        /* Enhanced Step Numbers */
        .step-number {
            width: 48px;
            height: 48px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            margin-right: 1.5rem;
            transition: all var(--transition-speed) ease;
        }

        .step-wrapper:hover .step-number {
            transform: scale(1.1) rotate(360deg);
        }

        /* Enhanced Testimonial Cards */
        .testimonial-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all var(--transition-speed) ease;
            background: white;
            box-shadow: var(--card-shadow);
        }

        .testimonial-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.15);
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            background: var(--primary-gradient);
            transition: all var(--transition-speed) ease;
        }

        .testimonial-card:hover .avatar {
            transform: scale(1.1);
        }

        /* Enhanced Navigation */
        .navbar {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9) !important;
            transition: color 0.3s ease;
        }

        .navbar-brand:hover {
            color: #fff !important;
        }

        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.9) !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #fff !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: #fff;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .navbar-nav .btn-signup {
            background: transparent;
            border: 2px solid #fff;
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .navbar-nav .btn-signup:hover {
            color: #fff !important;
            border-color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Enhanced Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
            transform: translateX(-100%);
            transition: all var(--transition-speed) ease;
        }

        .btn:hover::before {
            transform: translateX(0);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            border-color: transparent;
        }

        /* Enhanced Section Titles */
        .section-title {
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 3rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        /* Enhanced Feature Icons Animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .feature-icon i {
            animation: pulse 2s infinite;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0;
            }

            .feature-card {
                margin-bottom: 2rem;
            }

            .step-wrapper {
                margin-bottom: 2rem;
            }
        }


        /* Scroll Animations */
        .fade-up {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Enhanced Cards Hover Effect */
        .card {
            transition: all var(--transition-speed) ease;
        }

        .card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.15);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top" style="background: var(--primary-gradient);">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#" style="font-size: 1.7rem; letter-spacing: 1px;">
                <i class="fa-solid fa-wallet"></i> Expense Maker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-lg-center gap-lg-2">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="#testimonials">Testimonials</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link text-white px-3" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-light fw-semibold ms-lg-2 px-4 py-2 rounded-pill" href="register.php" style="border-width:2px;">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">Split Expenses Without the Hassle</h1>
                    <p class="lead mb-4">Track, manage, and settle group expenses effortlessly. No more awkward conversations about money.</p>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <div class="d-flex gap-3">
                            <a href="register.php" class="btn btn-primary btn-lg">Get Started</a>
                            <a href="#how-it-works" class="btn btn-outline-primary btn-lg">Learn More</a>
                        </div>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Expense Sharing" class="img-fluid rounded-3 shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage group expenses efficiently</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="card-title h5">Create Groups</h3>
                            <p class="card-text">Organize expenses by creating groups for different occasions - trips, roommates, events, and more.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <h3 class="card-title h5">Track Expenses</h3>
                            <p class="card-text">Add expenses with descriptions, amounts, and dates. Keep a clear record of who paid for what.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <h3 class="card-title h5">Automatic Splitting</h3>
                            <p class="card-text">Expenses are automatically split among group members, saving you time and reducing errors.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <h3 class="card-title h5">Settlement History</h3>
                            <p class="card-text">View your settlement history and keep track of who owes you and who you owe.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h3 class="card-title h5">Mobile Friendly</h3>
                            <p class="card-text">Access your expenses on any device. Perfect for adding expenses on the go.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle mb-3">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="card-title h5">Secure & Private</h3>
                            <p class="card-text">Your data is secure and private. Only group members can see the expenses they're part of.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">How It Works</h2>
                <p class="lead text-muted">Simple steps to manage your group expenses</p>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <img src="https://images.unsplash.com/photo-1554224154-26032ffc0d07?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="How It Works" class="img-fluid rounded-3 shadow-lg">
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="d-flex mb-4">
                        <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">1</div>
                        <div>
                            <h3 class="h5">Create a Group</h3>
                            <p>Start by creating a group for your shared expenses. Add members to the group.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">2</div>
                        <div>
                            <h3 class="h5">Add Expenses</h3>
                            <p>Add expenses to the group with details like amount, description, and date.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">3</div>
                        <div>
                            <h3 class="h5">Track Balances</h3>
                            <p>The app automatically calculates who owes whom and by how much.</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">4</div>
                        <div>
                            <h3 class="h5">Settle Up</h3>
                            <p>Mark expenses as settled when payments are made and keep track of your history.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold">What Our Users Say</h2>
                <p class="lead text-muted">Join thousands of satisfied users who manage their expenses with us</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <p class="card-text">"This app has made splitting expenses with my roommates so much easier. No more confusion or arguments about who paid for what!"</p>
                            <div class="d-flex align-items-center mt-3">
                                <div class="avatar bg-primary text-white rounded-circle me-3">JD</div>
                                <div>
                                    <h5 class="mb-0">John Doe</h5>
                                    <small class="text-muted">College Student</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <p class="card-text">"As someone who travels frequently with friends, this app has been a game-changer. It's so easy to track and split expenses on the go."</p>
                            <div class="d-flex align-items-center mt-3">
                                <div class="avatar bg-primary text-white rounded-circle me-3">SJ</div>
                                <div>
                                    <h5 class="mb-0">Sarah Johnson</h5>
                                    <small class="text-muted">Travel Enthusiast</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                            </div>
                            <p class="card-text">"I use this app for all my group projects. It helps me keep track of shared expenses and ensures everyone pays their fair share."</p>
                            <div class="d-flex align-items-center mt-3">
                                <div class="avatar bg-primary text-white rounded-circle me-3">MR</div>
                                <div>
                                    <h5 class="mb-0">Michael Rodriguez</h5>
                                    <small class="text-muted">Project Manager</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-8 text-center text-lg-start" data-aos="fade-right">
                    <h2 class="display-5 fw-bold">Ready to Simplify Your Expense Sharing?</h2>
                    <p class="lead">Join thousands of users who trust Expense Maker for their group expenses.</p>
                </div>
                <div class="col-lg-4 text-center text-lg-end" data-aos="fade-left">
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn btn-light btn-lg">Get Started Now</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-wallet"></i> Expense Maker</h5>
                    <p class="mb-0">Simplifying expense sharing for groups and individuals.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">Home</a></li>
                        <li><a href="#features" class="text-white-50">Features</a></li>
                        <li><a href="#how-it-works" class="text-white-50">How It Works</a></li>
                        <li><a href="#testimonials" class="text-white-50">Testimonials</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50"><i class="fas fa-envelope me-2"></i> support@expensemaker.com</a></li>
                        <li><a href="#" class="text-white-50"><i class="fas fa-phone me-2"></i> +1 (123) 456-7890</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 Expense Maker. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white-50 me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white-50 me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white-50 me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white-50"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Scroll Animation
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-up');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });

            fadeElements.forEach(element => {
                observer.observe(element);
            });

        });
    </script>
</body>
</html>