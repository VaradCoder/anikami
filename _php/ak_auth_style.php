<style>
/* ── Auth pages (login/register/forgot/reset/verify) — shared theme ──
   Uses the same CSS variables as the rest of the site (anikami.css,
   loaded by ak_page_head.php): --accent, --bg-card, --text-secondary, etc. */
.ak-auth-wrap {
  min-height: calc(100vh - var(--header-h));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  background:
    radial-gradient(ellipse 800px 500px at 50% 0%, rgba(200,16,46,.10), transparent 70%);
}
.ak-auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--bg-card);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: var(--radius-lg);
  padding: 36px 32px;
  box-shadow: 0 20px 50px rgba(0,0,0,.4);
}
.ak-auth-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  font-size: 20px;
  letter-spacing: .5px;
  color: var(--text-main);
  margin-bottom: 22px;
}
.ak-auth-logo i { color: var(--accent); }
.ak-auth-title {
  font-family: 'Cinzel', serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--text-main);
  text-align: center;
  margin: 0 0 6px;
}
.ak-auth-sub {
  font-size: 13px;
  color: var(--text-muted);
  text-align: center;
  margin: 0 0 26px;
}
.ak-auth-field {
  position: relative;
  margin-bottom: 16px;
}
.ak-auth-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
}
.ak-auth-field .ak-auth-input-wrap { position: relative; }
.ak-auth-field i.ak-auth-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 13px;
  pointer-events: none;
}
.ak-auth-field input[type="text"],
.ak-auth-field input[type="email"],
.ak-auth-field input[type="password"] {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: var(--radius-sm);
  padding: 11px 14px 11px 38px;
  font-size: 13px;
  color: var(--text-main);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.ak-auth-field input::placeholder { color: var(--text-muted); }
.ak-auth-field input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(200,16,46,.15);
}
.ak-auth-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--text-secondary);
  margin-bottom: 20px;
  cursor: pointer;
  user-select: none;
}
.ak-auth-checkbox input { accent-color: var(--accent); width: 14px; height: 14px; cursor: pointer; }
.ak-auth-btn {
  width: 100%;
  background: var(--accent);
  border: none;
  border-radius: var(--radius-sm);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  padding: 12px;
  cursor: pointer;
  transition: background .15s, box-shadow .15s;
}
.ak-auth-btn:hover { background: var(--accent-hover); box-shadow: 0 6px 18px rgba(200,16,46,.35); }
.ak-auth-links {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  font-size: 12px;
}
.ak-auth-links a { color: var(--text-secondary); text-decoration: none; transition: color .15s; }
.ak-auth-links a:hover { color: var(--accent); }
.ak-auth-links.center { justify-content: center; gap: 6px; }
.ak-auth-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 12.5px;
  line-height: 1.5;
  margin-bottom: 20px;
  word-break: break-word;
}
.ak-auth-alert i { margin-top: 1px; flex-shrink: 0; }
.ak-auth-alert.error { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
.ak-auth-alert.success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.25); color: #86efac; }
.ak-auth-alert a { color: inherit; text-decoration: underline; word-break: break-all; }
.ak-auth-icon-hero {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 18px;
  font-size: 22px;
}
.ak-auth-icon-hero.success { background: rgba(34,197,94,.12); color: #22c55e; }
.ak-auth-icon-hero.error { background: rgba(239,68,68,.12); color: #ef4444; }
.ak-auth-btn.secondary {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.12);
  color: var(--text-secondary);
  text-decoration: none;
  display: block;
  text-align: center;
}
.ak-auth-btn.secondary:hover { background: rgba(255,255,255,.1); box-shadow: none; color: var(--text-main); }
</style>
