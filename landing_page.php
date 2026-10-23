<?php
session_start();
$brandName = "DocuBiz";
$currentYear = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($brandName); ?> - Landing Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    /* Smooth Scrolling */
    html {
      scroll-behavior: smooth;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    body {
      background-color: #ffffff;
      color: #1e293b;
      line-height: 1.5;
      overflow-x: hidden;
    }

    /* Fixed Navigation Bar */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 60px;
      background-color: #122b49;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 50px;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
    }

    .brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: #ffffff;
      text-decoration: none;
      letter-spacing: -0.5px;
    }

    .nav-links {
      display: flex;
      gap: 35px;
      list-style: none;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
    }

    .nav-links a {
      color: #ffffff;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 400;
      transition: opacity 0.2s;
    }

    .nav-links a:hover {
      opacity: 0.8;
    }

    .btn-login {
      background-color: #ffffff;
      color: #122b49;
      padding: 6px 20px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
      text-decoration: none;
      transition: background-color 0.2s;
    }

    .btn-login:hover {
      background-color: #f1f5f9;
    }

    section {
      scroll-margin-top: 60px;
      min-height: calc(100vh - 60px);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    /* 1. HOME SECTION */
    #home {
      margin-top: 60px;
      background: linear-gradient(180deg, #122b49 0%, #a2caf8 40%, #c1e2fb 100%);
      padding: 40px 80px;
    }

    .home-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
      gap: 30px;
    }

    .home-content {
      flex: 1;
      max-width: 580px;
    }

    .home-content h1 {
      font-size: 2.8rem;
      font-weight: 700;
      color: #122b49;
      line-height: 1.15;
      margin-bottom: 18px;
    }

    .home-content p {
      font-size: 0.9rem;
      color: #1e293b;
      margin-bottom: 25px;
      line-height: 1.5;
    }

    .pills-grid {
      display: grid;
      grid-template-columns: repeat(2, auto);
      gap: 10px;
      justify-content: start;
      margin-bottom: 30px;
    }

    .pill {
      background-color: #ffffff;
      padding: 7px 15px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 500;
      color: #1e293b;
      box-shadow: 0 2px 5px rgba(0,0,0,0.04);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-get-started {
      background-color: #ffffff;
      color: #122b49;
      padding: 10px 24px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.88rem;
      text-decoration: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: transform 0.2s;
    }

    .btn-get-started:hover {
      transform: translateY(-2px);
    }

    .home-illustration {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    /* 2. FEATURES SECTION */
    #features {
      background-color: #ffffff;
      padding: 40px;
      align-items: center;
    }

    .section-title {
      font-size: 2rem;
      font-weight: 700;
      color: #122b49;
      text-align: center;
      margin-bottom: 30px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      max-width: 950px;
      width: 100%;
      margin: 0 auto;
    }

    .feature-card {
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px 18px;
      text-align: center;
      box-shadow: 0 6px 18px rgba(0,0,0,0.04);
      transition: transform 0.2s;
    }

    .feature-card:hover {
      transform: translateY(-3px);
    }

    .feature-icon {
      font-size: 1.8rem;
      margin-bottom: 10px;
    }

    .feature-card h3 {
      font-size: 0.9rem;
      font-weight: 600;
      color: #122b49;
      margin-bottom: 6px;
    }

    .feature-card p {
      font-size: 0.73rem;
      color: #64748b;
      line-height: 1.4;
    }

    /* 3. ABOUT SECTION - Pinalaki at pinaganda ang readability ng mga teksto */
    #about {
      background-color: #ffffff;
      padding: 0;
      justify-content: flex-start;
      position: relative;
    }

    .about-top-banner {
      width: 100%;
      height: 130px;
      background: linear-gradient(180deg, #7eb6f6 0%, #2563eb 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .about-top-banner h2 {
      color: #ffffff;
      font-size: 2.5rem;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .about-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      padding: 50px 30px;
    }

    .about-illustration-top-left {
      position: absolute;
      top: -45px;
      left: 7%;
      width: 180px;
      z-index: 5;
    }

    .about-illustration-bottom-right {
      position: absolute;
      bottom: 10px;
      right: 7%;
      width: 190px;
      z-index: 5;
    }

    .about-container {
      max-width: 780px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 32px;
      text-align: center;
      z-index: 3;
    }

    /* Mas malaki at madaling basahin na font size */
    .about-container p {
      font-size: 1.12rem;
      color: #1e293b;
      line-height: 1.75;
      font-weight: 400;
    }

    /* 4. CONTACT SECTION */
    #contact {
      background-color: #ffffff;
      padding-top: 15px;
      padding-bottom: 0;
      justify-content: space-between;
      height: calc(100vh - 60px);
      overflow: hidden;
    }

    .contact-wrapper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      padding-left: 70px;
      gap: 30px;
      flex: 1;
    }

    .contact-info {
      flex: 1;
      max-width: 480px;
    }

    .contact-info h2 {
      font-size: 2rem;
      font-weight: 700;
      color: #122b49;
      margin-bottom: 12px;
    }

    .contact-info p {
      font-size: 0.82rem;
      color: #334155;
      margin-bottom: 18px;
      line-height: 1.4;
    }

    .contact-detail-group {
      margin-bottom: 14px;
    }

    .contact-detail-header {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.88rem;
      font-weight: 700;
      color: #122b49;
      margin-bottom: 2px;
    }

    .contact-detail-value {
      font-size: 0.82rem;
      color: #334155;
    }

    .contact-form-box {
      background-color: #18324e;
      padding: 25px 40px;
      border-top-left-radius: 35px;
      border-bottom-left-radius: 35px;
      border-top-right-radius: 0px;
      border-bottom-right-radius: 0px;
      width: 520px;
      flex-shrink: 0;
      box-shadow: -10px 10px 30px rgba(0, 0, 0, 0.08);
    }

    .contact-form-box h3 {
      color: #ffffff;
      font-size: 1.1rem;
      margin-bottom: 12px;
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 10px;
    }

    .form-group label {
      display: block;
      color: #ffffff;
      font-size: 0.75rem;
      margin-bottom: 3px;
      font-weight: 400;
    }

    .form-group label span {
      color: #ef4444;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 8px 12px;
      border-radius: 8px;
      border: none;
      outline: none;
      background-color: #ffffff;
      font-size: 0.82rem;
      color: #1e293b;
    }

    .form-group textarea {
      resize: none;
      height: 55px;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px;
    }

    .btn-cancel {
      background-color: #ffffff;
      color: #1e293b;
      border: none;
      padding: 6px 18px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.78rem;
      cursor: pointer;
    }

    .btn-submit {
      background-color: #81c8c1;
      color: #18324e;
      border: none;
      padding: 6px 18px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.78rem;
      cursor: pointer;
    }

    /* Footer */
    footer {
      background-color: #122b49;
      color: #ffffff;
      padding: 12px 50px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      flex-shrink: 0;
    }

    .footer-brand h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: #ffffff;
    }

    .footer-col h4 {
      font-size: 0.72rem;
      margin-bottom: 2px;
      letter-spacing: 0.5px;
      color: #ffffff;
    }

    .footer-col ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 1px;
    }

    .footer-col ul li,
    .footer-col ul li a {
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.68rem;
      transition: color 0.2s;
    }

    .footer-col ul li a:hover {
      color: #ffffff;
    }

    .footer-copy {
      color: #94a3b8;
      font-size: 0.68rem;
    }

    @media (max-width: 900px) {
      .home-container, .contact-wrapper {
        flex-direction: column;
        padding-left: 20px;
        padding-right: 20px;
      }
      .features-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .contact-form-box {
        width: 100%;
        border-radius: 20px;
      }
      .nav-links {
        display: none;
      }
      .about-illustration-top-left,
      .about-illustration-bottom-right {
        display: none;
      }
      #contact {
        height: auto;
        min-height: calc(100vh - 60px);
      }
      footer {
        flex-direction: column;
        gap: 10px;
        text-align: center;
        padding: 15px;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <nav class="navbar">
    <a href="#home" class="brand"><?php echo htmlspecialchars($brandName); ?></a>
    
    <ul class="nav-links">
      <li><a href="#home">Home</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#contact">Contact Us</a></li>
    </ul>

    <a href="login.php" class="btn-login">Log In</a>
  </nav>

  <!-- 1. HOME SECTION -->
  <section id="home">
    <div class="home-container">
      <div class="home-content">
        <h1>Manage your business in one place.</h1>
        <p>Bring efficiency to your workflow with a centralized web-based business management and communication system designed to integrate essential business operations into a single platform.</p>
        
        <div class="pills-grid">
          <div class="pill">👤 Customize Roles</div>
          <div class="pill">📦 Monitor Inventory Records</div>
          <div class="pill">📄 Organize Documents</div>
          <div class="pill">📁 Schedule Projects</div>
        </div>

        <a href="#features" class="btn-get-started">Get Started &rarr;</a>
      </div>
      <div class="home-illustration">
        <svg width="420" height="310" viewBox="0 0 520 380" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="70" y="30" width="300" height="200" rx="14" fill="#fbbf24" stroke="#78350f" stroke-width="4"/>
          <rect x="90" y="60" width="110" height="70" rx="6" fill="#ffffff"/>
          <rect x="215" y="60" width="135" height="100" rx="6" fill="#ffffff"/>
          <circle cx="245" cy="90" r="14" fill="#f87171"/>
          <rect x="90" y="145" width="110" height="50" rx="6" fill="#f87171"/>
          <path d="M80 260L290 260L350 300L140 300Z" fill="#fde047" stroke="#78350f" stroke-width="3"/>
          <g transform="translate(340, 90)">
            <rect x="0" y="0" width="120" height="135" rx="10" fill="#fde047" stroke="#78350f" stroke-width="3" transform="rotate(12)"/>
            <rect x="15" y="25" width="90" height="85" fill="#ffffff"/>
            <circle cx="35" cy="45" r="8" fill="#f87171"/>
            <circle cx="60" cy="45" r="8" fill="#a855f7"/>
            <circle cx="85" cy="70" r="8" fill="#38bdf8"/>
          </g>
          <g transform="translate(300, 210)">
            <path d="M20 30L140 10L160 70L40 90Z" fill="#fef08a" stroke="#78350f" stroke-width="3"/>
            <rect x="30" y="40" width="120" height="80" rx="6" fill="#ffffff" stroke="#78350f" stroke-width="3"/>
            <line x1="45" y1="60" x2="110" y2="60" stroke="#cbd5e1" stroke-width="6" stroke-linecap="round"/>
            <line x1="45" y1="80" x2="90" y2="80" stroke="#cbd5e1" stroke-width="6" stroke-linecap="round"/>
          </g>
        </svg>
      </div>
    </div>
  </section>

  <!-- 2. FEATURES SECTION -->
  <section id="features">
    <h2 class="section-title">Features</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">💬</div>
        <h3>Communicate in Real-Time</h3>
        <p>Message employees with ease, send files, and plan projects together.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Staff Management</h3>
        <p>Customize roles for staff members to assign custom permissions.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🖥️</div>
        <h3>Monitor Inventory</h3>
        <p>Categorize and keep track of inventory items and where they're located.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📁</div>
        <h3>Organize Documents</h3>
        <p>Access files in a shared repository available across departments.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🤝</div>
        <h3>Plan and Schedule Projects</h3>
        <p>Plan upcoming projects that are connected to a shared calendar.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📅</div>
        <h3>Task Assignments</h3>
        <p>Assign tasks and set deadlines to multiple users.</p>
      </div>
    </div>
  </section>

  <!-- 3. ABOUT SECTION - Pinalaki at pinagitna ang mga teksto -->
  <section id="about">
    <div class="about-top-banner">
      <h2>About</h2>
    </div>
    
    <div class="about-body">
      <svg class="about-illustration-top-left" viewBox="0 0 220 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="25" y="35" width="110" height="130" rx="12" fill="#fbbf24" stroke="#78350f" stroke-width="3" transform="rotate(-12 25 35)"/>
        <rect x="45" y="25" width="115" height="140" rx="8" fill="#ffffff" stroke="#78350f" stroke-width="3" transform="rotate(4 45 25)"/>
        <line x1="70" y1="65" x2="135" y2="65" stroke="#cbd5e1" stroke-width="6" stroke-linecap="round"/>
        <rect x="65" y="85" width="14" height="14" rx="3" fill="#fbbf24"/>
        <line x1="90" y1="92" x2="135" y2="92" stroke="#cbd5e1" stroke-width="6" stroke-linecap="round"/>
        <rect x="65" y="110" width="14" height="14" rx="3" fill="#fbbf24"/>
        <line x1="90" y1="117" x2="125" y2="117" stroke="#cbd5e1" stroke-width="6" stroke-linecap="round"/>
        <circle cx="50" cy="115" r="24" fill="#ffffff" stroke="#78350f" stroke-width="4"/>
        <line x1="33" y1="132" x2="15" y2="150" stroke="#78350f" stroke-width="6" stroke-linecap="round"/>
        <path d="M140 30L165 55L150 70L125 45Z" fill="#fbbf24" stroke="#78350f" stroke-width="3"/>
        <path d="M125 45L115 55L125 65L135 55Z" fill="#78350f"/>
      </svg>

      <div class="about-container">
        <p>DocuBiz is a student-developed system that integrates core business processes to improve workflows and improve convenience.</p>
        <p>We aim to provide a role-based approach to enhance the efficiency, accessibility and organization of business processes on a single web-based platform.</p>
        <p>We seek to increase data accessibility, decrease manual work and make basic administrative tasks more organized. Overall, the system aims to offer a useful web-based solution that can help administrators and staff manage important business operations on a single platform.</p>
      </div>

      <svg class="about-illustration-bottom-right" viewBox="0 0 220 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="25" y="30" width="140" height="120" rx="8" fill="#fef08a" stroke="#78350f" stroke-width="3"/>
        <path d="M25 70H165M25 110H165M75 30V150M120 30V150" stroke="#fde047" stroke-width="2"/>
        <circle cx="95" cy="55" r="22" fill="#fef08a" stroke="#78350f" stroke-width="3"/>
        <path d="M85 77H105V88H85Z" fill="#78350f"/>
        <circle cx="55" cy="140" r="32" fill="#ffffff" stroke="#78350f" stroke-width="4"/>
        <circle cx="55" cy="140" r="20" fill="#fbbf24"/>
        <circle cx="55" cy="140" r="8" fill="#ffffff"/>
        <path d="M85 110L55 140" stroke="#78350f" stroke-width="5" stroke-linecap="round"/>
        <rect x="125" y="100" width="60" height="45" rx="6" fill="#fbbf24" stroke="#78350f" stroke-width="3"/>
        <rect x="15" y="100" width="45" height="32" rx="4" fill="#ffffff" stroke="#78350f" stroke-width="3"/>
      </svg>
    </div>
  </section>

  <!-- 4. CONTACT US SECTION -->
  <section id="contact">
    <div class="contact-wrapper">
      <div class="contact-info">
        <h2>Contact Us</h2>
        <p>Want to learn more about our system?<br>Do you have questions about how to use it?<br>Get in touch with us! We would love to hear from you.</p>
        
        <div class="contact-detail-group">
          <div class="contact-detail-header">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            Email
          </div>
          <div class="contact-detail-value">docubiz.business@gmail.com</div>
        </div>

        <div class="contact-detail-group">
          <div class="contact-detail-header">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            Phone
          </div>
          <div class="contact-detail-value">+63 917 816 8625</div>
        </div>
      </div>

      <div class="contact-form-box">
        <h3>Contact Form</h3>
        <form action="#" method="POST">
          <div class="form-group">
            <label>First name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="first_name" required>
          </div>
          <div class="form-group">
            <label>Last name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="last_name" required>
          </div>
          <div class="form-group">
            <label>Email <span style="color:#ef4444;">*</span></label>
            <input type="email" name="email" required>
          </div>
          <div class="form-group">
            <label>Share your inquiries or concerns <span style="color:#ef4444;">*</span></label>
            <textarea name="message" required></textarea>
          </div>
          <div class="form-actions">
            <button type="reset" class="btn-cancel">Cancel</button>
            <button type="submit" class="btn-submit">Submit</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Footer -->
    <footer>
      <div class="footer-brand">
        <h3><?php echo htmlspecialchars($brandName); ?></h3>
      </div>
      <div class="footer-col">
        <h4>CONTACTS</h4>
        <ul>
          <li>docubiz.business@gmail.com</li>
          <li>+63 917 816 8625</li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>QUICK LINKS</h4>
        <ul>
          <li><a href="#home">Home</a></li>
          <li><a href="#features">Features</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#contact">Contact Us</a></li>
        </ul>
      </div>
      <div class="footer-copy">
        &copy; <?php echo $currentYear; ?> DocuBiz. All rights reserved.
      </div>
    </footer>
  </section>

</body>
</html>