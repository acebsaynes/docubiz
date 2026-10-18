<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Calendar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    body {
      background-color: #d8dcdb;
      color: #333;
      min-height: 100vh;
    }

    .app-layout {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .topbar {
      background-color: #122b49;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      color: #ffffff;
    }

    .brand-container {
      display: flex;
      align-items: center;
      gap: 15px;
      width: 200px;
    }

    .hamburger-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .brand-name {
      font-size: 1.4rem;
      font-weight: 600;
      letter-spacing: -0.5px;
    }

    .search-bar {
      position: relative;
      width: 450px;
      display: flex;
      align-items: center;
    }

    .search-bar input {
      width: 100%;
      padding: 8px 45px 8px 16px;
      border-radius: 20px;
      border: none;
      outline: none;
      background-color: #ffffff;
      font-size: 0.85rem;
      color: #2d3748;
    }

    .search-bar input::placeholder {
      color: #8fa0b5;
    }

    .search-btn {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: #122b49;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .icon-btn {
      background: none;
      border: none;
      color: #ffffff;
      cursor: pointer;
      display: flex;
      align-items: center;
      opacity: 0.9;
    }

    .icon-btn:hover {
      opacity: 1;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: 10px;
    }

    .profile-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background-color: #2b5585;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .profile-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .user-name {
      font-size: 0.82rem;
      font-weight: 500;
    }

    .user-role {
      font-size: 0.72rem;
      color: #9bb3d1;
    }

    .main-body {
      display: flex;
      flex: 1;
    }

    .sidebar {
      width: 210px;
      background-color: #122b49;
      padding-top: 15px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-menu {
      display: flex;
      flex-direction: column;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 20px;
      color: #b0c4de;
      text-decoration: none;
      font-size: 0.88rem;
      transition: all 0.2s ease;
    }

    .menu-item:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.05);
    }

    .menu-item.active {
      color: #ffffff;
      background-color: #274b73;
    }

    .content-area {
      flex: 1;
      padding: 30px 40px;
      background-color: #d5dad9;
    }

    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      color: #2a343d;
      line-height: 1.1;
    }

    .page-subtitle {
      font-size: 0.82rem;
      color: #637381;
      margin-top: 4px;
    }

    .btn-add-event {
      background-color: #72dbd9;
      color: #1a365d;
      border: none;
      border-radius: 18px;
      padding: 8px 18px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      transition: transform 0.1s ease, background-color 0.2s ease;
    }

    .btn-add-event:hover {
      background-color: #5ececb;
    }

    .calendar-grid-container {
      display: flex;
      gap: 25px;
    }

    .left-calendar-section {
      flex: 2;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .right-events-section {
      flex: 1;
    }

    .calendar-control-bar {
      background-color: #ffffff;
      border-radius: 10px;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }

    .month-nav {
      display: flex;
      align-items: center;
      gap: 30px;
    }

    .nav-arrow {
      background: none;
      border: none;
      font-size: 1rem;
      color: #4a5568;
      cursor: pointer;
      font-weight: 600;
    }

    .current-month {
      font-size: 0.95rem;
      font-weight: 600;
      color: #2d3748;
    }

    .btn-today {
      background: none;
      border: none;
      font-size: 0.85rem;
      font-weight: 500;
      color: #2d3748;
      cursor: pointer;
    }

    .full-calendar-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }

    .calendar-table-header {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      text-align: center;
      font-size: 0.8rem;
      font-weight: 600;
      color: #4a5568;
      padding-bottom: 15px;
    }

    .calendar-month-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      border-top: 1px solid #edf2f7;
      border-left: 1px solid #edf2f7;
    }

    .day-box {
      min-height: 85px;
      border-right: 1px solid #edf2f7;
      border-bottom: 1px solid #edf2f7;
      padding: 6px;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .day-number {
      font-size: 0.78rem;
      color: #2d3748;
      font-weight: 500;
      margin-bottom: 4px;
    }

    .event-badge {
      font-size: 0.62rem;
      padding: 3px 4px;
      border-radius: 4px;
      color: #2d3748;
      line-height: 1.1;
      font-weight: 500;
    }

    .badge-catering {
      background-color: #d8b4fe;
    }

    .badge-inventory {
      background-color: #fca5a5;
    }

    .badge-meeting {
      background-color: #86efac;
    }

    .calendar-legend {
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 5px 0;
      font-size: 0.75rem;
      color: #4a5568;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
    }

    .dot-catering { background-color: #d8b4fe; }
    .dot-inventory { background-color: #fca5a5; }
    .dot-meeting { background-color: #86efac; }
    .dot-other { background-color: #9ca3af; }

    .card {
      background: #ffffff;
      border-radius: 12px;
      padding: 22px 25px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      border-bottom: 1px solid #edf2f7;
      padding-bottom: 12px;
    }

    .card-header h2 {
      font-size: 1rem;
      font-weight: 600;
      color: #2d3748;
    }

    .view-all {
      font-size: 0.8rem;
      color: #63b3ed;
      text-decoration: none;
    }

    .upcoming-events-list {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .upcoming-event-item {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .date-badge {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #2d3748;
      line-height: 1;
    }

    .date-badge .month {
      font-size: 0.7rem;
    }

    .date-badge .day {
      font-size: 1rem;
      margin-top: 3px;
    }

    .event-details {
      display: flex;
      flex-direction: column;
    }

    .event-details h3 {
      font-size: 0.9rem;
      font-weight: 600;
      color: #2d3748;
    }

    .event-details .meta {
      font-size: 0.72rem;
      color: #718096;
      margin-top: 3px;
    }

    .event-details .location {
      font-size: 0.72rem;
      color: #a0aec0;
    }

    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal-container {
      width: 100%;
      max-width: 560px;
      background-color: #ffffff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      transform: scale(0.92);
      transition: transform 0.25s ease;
    }

    .modal-overlay.active .modal-container {
      transform: scale(1);
    }

    .modal-header {
      background-color: #122b49;
      padding: 16px 24px;
      color: #ffffff;
    }

    .modal-header h2 {
      font-size: 1.35rem;
      font-weight: 600;
      letter-spacing: -0.3px;
    }

    .modal-body {
      padding: 24px 28px;
    }

    .modal-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px 24px;
    }

    .form-field {
      display: flex;
      flex-direction: column;
    }

    .form-field label {
      font-size: 0.82rem;
      font-weight: 600;
      color: #122b49;
      margin-bottom: 6px;
    }

    .form-field input,
    .form-field select,
    .form-field textarea {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.82rem;
      color: #2d3748;
      outline: none;
      background-color: #ffffff;
    }

    .form-field input[type="date"],
    .form-field input[type="time"] {
      color: #4a5568;
      cursor: pointer;
    }

    .form-field input::placeholder,
    .form-field textarea::placeholder {
      color: #cbd5e0;
    }

    .form-field select {
      color: #a0aec0;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a0aec0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 14px;
    }

    .form-field textarea {
      resize: none;
      height: 80px;
    }

    .modal-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
    }

    .btn-modal-cancel {
      padding: 8px 22px;
      border: 1px solid #e2e8f0;
      background-color: #ffffff;
      color: #4a5568;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-modal-save {
      padding: 8px 20px;
      border: none;
      background-color: #72dbd9;
      color: #1a365d;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
    }

    @media (max-width: 900px) {
      .calendar-grid-container {
        flex-direction: column;
      }

      .sidebar {
        width: 60px;
      }

      .sidebar .menu-item span {
        display: none;
      }

      .search-bar {
        width: 200px;
      }

      .modal-form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <div class="app-layout">
    <header class="topbar">
      <div class="brand-container">
        <button class="hamburger-btn">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <span class="brand-name">DocuBiz</span>
      </div>

      <div class="search-bar">
        <input type="text" placeholder="Search events...">
        <button type="button" class="search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>

      <div class="topbar-actions">
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </button>
        <button class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>

        <div class="user-profile">
          <div class="profile-avatar">JL</div>
          <div class="profile-info">
            <span class="user-name">Jana Lei Lumbreras</span>
            <span class="user-role">Admin Staff</span>
          </div>
        </div>
      </div>
    </header>

    <div class="main-body">
   <aside class="sidebar">
  <nav class="sidebar-menu">
    <a href="dashboard.html" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
      <span>Dashboard</span>
    </a>
    <a href="messages.html" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
      <span>Messages</span>
    </a>
    <a href="calendar.html" class="menu-item active">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      <span>Calendar</span>
    </a>
    <a href="#" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
      <span>Projects</span>
    </a>
    <a href="#" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
      <span>Inventory</span>
    </a>
    <a href="#" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
      <span>Documents</span>
    </a>
    <a href="staff_management.html" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      <span>Staff Management</span>
    </a>
    <a href="#" class="menu-item">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
      <span>Finances</span>
    </a>
  </nav>
</aside>

      <main class="content-area">
        <div class="page-header">
          <div>
            <h1 class="page-title">Calendar</h1>
            <p class="page-subtitle">Manage catering events, deliveries, and important schedules.</p>
          </div>
          <button class="btn-add-event" id="openModalBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Event
          </button>
        </div>

        <div class="calendar-grid-container">
          <div class="left-calendar-section">
            <div class="calendar-control-bar">
              <div class="month-nav">
                <button class="nav-arrow">&lt;</button>
                <span class="current-month">August 2026</span>
                <button class="nav-arrow">&gt;</button>
              </div>
              <button class="btn-today">Today</button>
            </div>

            <div class="full-calendar-card">
              <div class="calendar-table-header">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
              </div>

              <div class="calendar-month-grid">
                <div class="day-box"><span class="day-number">26</span></div>
                <div class="day-box"><span class="day-number">27</span></div>
                <div class="day-box"><span class="day-number">28</span></div>
                <div class="day-box"><span class="day-number">29</span></div>
                <div class="day-box"><span class="day-number">30</span></div>
                <div class="day-box"><span class="day-number">31</span></div>
                <div class="day-box"><span class="day-number">1</span></div>

                <div class="day-box"><span class="day-number">2</span></div>
                <div class="day-box"><span class="day-number">3</span></div>
                <div class="day-box"><span class="day-number">4</span></div>
                <div class="day-box"><span class="day-number">5</span></div>
                <div class="day-box"><span class="day-number">6</span></div>
                <div class="day-box"><span class="day-number">7</span></div>
                <div class="day-box"><span class="day-number">8</span></div>

                <div class="day-box"><span class="day-number">9</span></div>
                <div class="day-box"><span class="day-number">10</span></div>
                <div class="day-box"><span class="day-number">11</span></div>
                <div class="day-box"><span class="day-number">12</span></div>
                <div class="day-box"><span class="day-number">13</span></div>
                <div class="day-box"><span class="day-number">14</span></div>
                <div class="day-box">
                  <span class="day-number">15</span>
                  <div class="event-badge badge-catering">Catering Event Birthday Party</div>
                </div>

                <div class="day-box"><span class="day-number">16</span></div>
                <div class="day-box">
                  <span class="day-number">17</span>
                  <div class="event-badge badge-inventory">Inventory Restock</div>
                </div>
                <div class="day-box"><span class="day-number">18</span></div>
                <div class="day-box"><span class="day-number">19</span></div>
                <div class="day-box">
                  <span class="day-number">20</span>
                  <div class="event-badge badge-meeting">Meeting</div>
                </div>
                <div class="day-box"><span class="day-number">21</span></div>
                <div class="day-box"><span class="day-number">22</span></div>

                <div class="day-box"><span class="day-number">23</span></div>
                <div class="day-box"><span class="day-number">24</span></div>
                <div class="day-box"><span class="day-number">25</span></div>
                <div class="day-box"><span class="day-number">26</span></div>
                <div class="day-box"><span class="day-number">27</span></div>
                <div class="day-box"><span class="day-number">28</span></div>
                <div class="day-box"><span class="day-number">29</span></div>

                <div class="day-box"><span class="day-number">30</span></div>
                <div class="day-box"><span class="day-number">31</span></div>
                <div class="day-box"></div>
                <div class="day-box"></div>
                <div class="day-box"></div>
                <div class="day-box"></div>
                <div class="day-box"></div>
              </div>
            </div>

            <div class="calendar-legend">
              <div class="legend-item">
                <span class="legend-dot dot-catering"></span>
                <span>Catering Event</span>
              </div>
              <div class="legend-item">
                <span class="legend-dot dot-inventory"></span>
                <span>Inventory Restock</span>
              </div>
              <div class="legend-item">
                <span class="legend-dot dot-meeting"></span>
                <span>Meeting</span>
              </div>
              <div class="legend-item">
                <span class="legend-dot dot-other"></span>
                <span>Other</span>
              </div>
            </div>
          </div>

          <div class="right-events-section">
            <div class="card">
              <div class="card-header">
                <h2>Upcoming Events</h2>
                <a href="#" class="view-all">View All</a>
              </div>

              <div class="upcoming-events-list">
                <div class="upcoming-event-item">
                  <div class="date-badge badge-catering">
                    <span class="month">AUG</span>
                    <span class="day">15</span>
                  </div>
                  <div class="event-details">
                    <h3>Birthday Party</h3>
                    <span class="meta">80 Guest • 4:00 PM</span>
                    <span class="location">Location</span>
                  </div>
                </div>

                <div class="upcoming-event-item">
                  <div class="date-badge badge-inventory">
                    <span class="month">AUG</span>
                    <span class="day">17</span>
                  </div>
                  <div class="event-details">
                    <h3>Inventory Restock</h3>
                    <span class="meta">80 Guest • 4:00 PM</span>
                    <span class="location">Location</span>
                  </div>
                </div>

                <div class="upcoming-event-item">
                  <div class="date-badge badge-meeting">
                    <span class="month">AUG</span>
                    <span class="day">20</span>
                  </div>
                  <div class="event-details">
                    <h3>Meeting</h3>
                    <span class="meta">80 Guest • 4:00 PM</span>
                    <span class="location">Location</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-overlay" id="addEventModal">
    <div class="modal-container">
      <div class="modal-header">
        <h2>Add Event</h2>
      </div>
      <div class="modal-body">
        <form onsubmit="event.preventDefault();">
          <div class="modal-form-grid">
            <div class="form-field">
              <label>Event Name</label>
              <input type="text" placeholder="Enter event name">
            </div>

            <div class="form-field">
              <label>Start Time</label>
              <input type="time">
            </div>

            <div class="form-field">
              <label>Date</label>
              <input type="date">
            </div>

            <div class="form-field">
              <label>End Time</label>
              <input type="time">
            </div>

            <div class="form-field">
              <label>Event Type</label>
              <select>
                <option value="" disabled selected>Catering Event</option>
                <option value="catering">Catering Event</option>
                <option value="inventory">Inventory Restock</option>
                <option value="meeting">Meeting</option>
              </select>
            </div>

            <div class="form-field">
              <label>Location</label>
              <input type="text" placeholder="Enter Location">
            </div>

            <div class="form-field">
              <label>Event Description</label>
              <textarea placeholder="Event Description (optional)"></textarea>
            </div>

            <div class="form-field">
              <label>Assigned Staff</label>
              <select style="margin-bottom: 12px;">
                <option value="" disabled selected>Select Staff</option>
              </select>
              <label>Assigned Staff</label>
              <textarea placeholder="Add notes (optional)"></textarea>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="closeModalBtn">Cancel</button>
            <button type="submit" class="btn-modal-save" id="saveModalBtn">Save Event</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const saveModalBtn = document.getElementById('saveModalBtn');
    const modalOverlay = document.getElementById('addEventModal');

    openModalBtn.addEventListener('click', () => {
      modalOverlay.classList.add('active');
    });

    closeModalBtn.addEventListener('click', () => {
      modalOverlay.classList.remove('active');
    });

    saveModalBtn.addEventListener('click', () => {
      modalOverlay.classList.remove('active');
    });

    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        modalOverlay.classList.remove('active');
      }
    });
  </script>

</body>
</html>