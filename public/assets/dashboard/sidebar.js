    /* ---------------- SIDEBAR active (client) ---------------- */
    const sidebarLinks = qa('.dashboard-sidebar a');
    const currentPath = normalizePath(location.pathname || location.href);
    function bestMatch(links, current){
      let best=null, bestScore=-1; const curSeg = current.split('/').filter(Boolean);
      links.forEach(a=>{
        try{
          let p = new URL(a.href, location.origin).pathname; if(p.length>1 && p.endsWith('/')) p = p.slice(0,-1);
          const seg = p.split('/').filter(Boolean);
          let i=0; while(i<seg.length && i<curSeg.length && seg[i]===curSeg[i]) i++;
          const score = i;
          if(score>0 || (seg.length===0 && curSeg.length===0)){
            if(score>bestScore){ bestScore=score; best=a; }
          }
        }catch(e){}
      });
      return best;
    }
    function refreshSidebarActive(){ sidebarLinks.forEach(a=>a.classList.remove('active')); const winner = bestMatch(sidebarLinks, currentPath); if(winner) winner.classList.add('active'); }
    if(sidebarLinks.length){
      sidebarLinks.forEach(a=> a.addEventListener('click', ()=>{ sidebarLinks.forEach(x=>x.classList.remove('active')); a.classList.add('active'); }));
      refreshSidebarActive();
    }


    /* ---------------- adjust pill radius (optional) ---------------- */
    try { const primary = q('.dashboard-sidebar > ul > li > a'); const pills = qa('.sidebar-categories a'); if(primary && pills.length){ const cs = window.getComputedStyle(primary); const br = cs.borderRadius || cs.getPropertyValue('border-radius') || null; if(br) pills.forEach(p=> p.style.borderRadius = br); } } catch(e){}
