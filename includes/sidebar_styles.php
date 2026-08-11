<style>
    body {
        background-color: #f8f9fa;
    }
    
    #sidebar {
        width: 220px;
        background-color: #0b0f4a;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1030;
        overflow-y: auto;
    }
    
    #sidebar .nav-link {
        color: #cfd8ff;
        border-radius: 8px;
        margin: 0 8px;
        transition: all 0.2s;
    }
    
    #sidebar .nav-link.active,
    #sidebar .nav-link:hover {
        background-color: #1a25a0;
        color: #ffffff !important;
        font-weight: 500;
    }
    
    #sidebar .nav-link i {
        min-width: 24px;
        text-align: center;
    }
    
    .user-avatar {
        color: #ffffff;
        opacity: 0.9;
    }

    .user-avatar i {
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
    
    .main-content {
        margin-left: 220px;
        padding: 30px;
        min-height: 100vh;
    }
    
   
    .utility-card {
        border-radius: 8px;
        transition: transform 0.2s;
    }
    
    .utility-card:hover {
        transform: translateY(-5px);
    }
    
    .border-left-primary {
        border-left: 4px solid #4e73df;
    }
    
    .border-left-success {
        border-left: 4px solid #1cc88a;
    }
    
    .border-left-info {
        border-left: 4px solid #36b9cc;
    }
    
    .border-left-warning {
        border-left: 4px solid #f6c23e;
    }
    
    .border-left-danger {
        border-left: 4px solid #e74a3b;
    }
    
    .border-left-secondary {
        border-left: 4px solid #858796;
    }
    
    
    @media (max-width: 768px) {
        #sidebar {
            margin-left: -220px;
        }
        
        #sidebar.show {
            margin-left: 0;
        }
        
        .main-content {
            margin-left: 0;
        }
    }
</style>