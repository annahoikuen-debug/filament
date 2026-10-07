// Main JavaScript for the あんしん website
document.addEventListener('DOMContentLoaded', function() {
  // Mobile menu toggle (if needed in future)
  const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
  const mobileMenu = document.querySelector('.mobile-menu');
  
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', function() {
      mobileMenu.classList.toggle('active');
    });
  }
  
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
  
  // Form validation enhancement
  const forms = document.querySelectorAll('.c-form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      // HTML5 validation will handle most cases
      // Additional custom validation can be added here if needed
    });
  });
  
  // Add active class to current nav item based on URL
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll('.c-header__nav ul li a');
  
  navLinks.forEach(link => {
    const linkPath = link.getAttribute('href');
    // Handle exact matches and index.html special case
    if (linkPath === 'index.html' && currentPath === '/' || 
        linkPath === currentPath ||
        (linkPath !== 'index.html' && currentPath.endsWith(linkPath))) {
      link.parentElement.classList.add('active');
    }
  });
  
  // Add animation on scroll for elements
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
      }
    });
  }, observerOptions);
  
  // Observe elements with animation classes
  const animatedElements = document.querySelectorAll('.animate-on-scroll');
  animatedElements.forEach(element => {
    observer.observe(element);
  });
  
  // Add CSS for animations
  const style = document.createElement('style');
  style.textContent = `
    .animate-in {
      opacity: 1 !important;
      transform: translateY(0) !important;
    }
    
    .animate-on-scroll {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }
    
    @media (prefers-reduced-motion: reduce) {
      .animate-on-scroll {
        transition: none;
      }
    }
  `;
  document.head.appendChild(style);
});

// Handle form submissions with feedback (demo version)
function handleFormSubmit(formId, successMessage) {
  const form = document.getElementById(formId);
  if (!form) return;
  
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span> 送信中...';
    }
    
    // Simulate API delay
    setTimeout(() => {
      // Reset form
      form.reset();
      
      // Show success message
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
      
      alert(successMessage);
    }, 1500);
  });
}

// Initialize form handlers when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  handleFormSubmit('catalogRequestForm', '資料請求ありがとうございます！自動返信メールを送信いたしました。');
  handleFormSubmit('inquiryForm', 'お問い合わせありがとうございます！2営業日以内にご連絡いたします。');
});