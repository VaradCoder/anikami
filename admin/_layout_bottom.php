    </div>
  </div>
</div>
<script>
(function(){
  var btn = document.getElementById('akAdminSidebarToggle');
  var sidebar = document.getElementById('akAdminSidebar');
  if (window.innerWidth <= 860 && btn) btn.style.display = 'inline-flex';
  if (btn && sidebar) btn.addEventListener('click', function(){ sidebar.classList.toggle('open'); });
})();
</script>
</body>
</html>
