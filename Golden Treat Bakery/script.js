// navbar
fetch("Navbar.html")
    .then(response => response.text())
    .then(data => {
      document.getElementById("navbar-placeholder").innerHTML = data;

      // Highlight active link
      const currentPage = window.location.pathname.split("/").pop();
      document.querySelectorAll("#navbar a").forEach(link => {
        if (link.getAttribute("href") === currentPage) {
          link.classList.add("active");
        }
      });
    });
// footer
fetch("footer.html")
    .then(response => response.text())
    .then(data => {
      document.getElementById("footer-placeholder").innerHTML = data;
    });