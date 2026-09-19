// ===== DATA STORE =====
var DB={
  getUsers(){return JSON.parse(localStorage.getItem('al_users')||'[]')},
  setUsers(u){localStorage.setItem('al_users',JSON.stringify(u));FS.sync('users',u)},
  getLogs(){return JSON.parse(localStorage.getItem('al_logs')||'[]')},
  setLogs(l){localStorage.setItem('al_logs',JSON.stringify(l));FS.sync('logs',l)},
  getSession(){return JSON.parse(localStorage.getItem('al_sess')||'null')},
  setSession(s){localStorage.setItem('al_sess',JSON.stringify(s))},
  clearSession(){localStorage.removeItem('al_sess')}
};
// ===== FIRESTORE SYNC =====
var FS={
  sync:function(col,data){
    try{db.collection('fittracker').doc(col).set({data:JSON.parse(JSON.stringify(data)),updated:Date.now()})}
    catch(e){console.warn('FS sync error:',e)}
  },
  load:function(col){
    return db.collection('fittracker').doc(col).get().then(function(doc){
      if(doc.exists&&doc.data().data)return doc.data().data;
      return null;
    }).catch(function(e){console.warn('FS load error:',e);return null})
  },
  init:function(){
    var localUsers=DB.getUsers();
    var localLogs=DB.getLogs();
    if(localUsers.length>0){FS.sync('users',localUsers)}
    if(localLogs.length>0){FS.sync('logs',localLogs)}
    return Promise.all([FS.load('users'),FS.load('logs')]).then(function(results){
      var fsUsers=results[0],fsLogs=results[1];
      if(fsUsers&&fsUsers.length>localUsers.length){DB.setUsers(fsUsers);DB.setLogs(fsLogs||[])}
      else if(fsUsers&&fsUsers.length>0&&localUsers.length===0){DB.setUsers(fsUsers);DB.setLogs(fsLogs||[])}
      return true;
    })
  }
};
function hash(s){let h=0;for(let i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0}return'H'+Math.abs(h).toString(36)}
function uid(){return Date.now().toString(36)+Math.random().toString(36).slice(2,7)}
function age(dob){if(!dob)return'-';const b=new Date(dob),n=new Date();let a=n.getFullYear()-b.getFullYear();if(n.getMonth()<b.getMonth()||(n.getMonth()===b.getMonth()&&n.getDate()<b.getDate()))a--;return a}
function fmt(d){return new Date(d).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'})}
function toast(m,t){t=t||'success';const e=document.createElement('div');e.className='toast toast-'+t;e.textContent=m;document.body.appendChild(e);setTimeout(function(){e.remove()},3000)}
function calcWHR(p,g){return p&&g?(p/g).toFixed(3):'-'}
function whrCat(w,sex){if(!w||w==='-')return{l:'-',c:'',d:''};if(sex==='male'){if(w<.90)return{l:'Ideal',c:'badge-green',d:'Risiko kesehatan rendah. Pertahankan pola hidup sehat.'};if(w<1.0)return{l:'Risiko Sedang',c:'badge-yellow',d:'Risiko kesehatan meningkat. Disarankan kurangi lemak perut.'};return{l:'Risiko Tinggi',c:'badge-red',d:'Risiko penyakit kardiovalskular tinggi. Konsultasi dokter.'}}else{if(w<.80)return{l:'Ideal',c:'badge-green',d:'Risiko kesehatan rendah. Pertahankan pola hidup sehat.'};if(w<.85)return{l:'Risiko Sedang',c:'badge-yellow',d:'Risiko kesehatan meningkat. Disarankan kurangi lemak perut.'};return{l:'Risiko Tinggi',c:'badge-red',d:'Risiko penyakit kardiovalskular tinggi. Konsultasi dokter.'}}}
function fatigueScore(l){
  var rpe=l.rpe||0,dur=l.durasi||0,doms=l.doms||0,stres=l.stres||0;
  var load=(rpe/10)*(dur/60);
  var delta=(l.hr_h||0)-(l.hr_s||0);
  var c=(load*0.35+doms*0.25+stres*0.25+(delta>20?15:delta>10?10:0)*0.15);
  if(c>=7)return{s:c.toFixed(1),l:'Risiko Overtraining',c:'badge-red',z:'red'};
  if(c>=5)return{s:c.toFixed(1),l:'Tinggi',c:'badge-yellow',z:'yellow'};
  if(c>=3)return{s:c.toFixed(1),l:'Sedang',c:'badge-blue',z:'blue'};
  return{s:c.toFixed(1),l:'Rendah',c:'badge-green',z:'green'};
}
// ===== SEED =====
function seedDemo(){
  var users=DB.getUsers();
  var hasOldAdmin=users.some(function(u){return u.email==='admin@athletelog.com'});
  if(hasOldAdmin){localStorage.removeItem('al_users');localStorage.removeItem('al_logs');users=[];}
  var hasNewAdmin=users.some(function(u){return u.email==='admin'});
  if(users.length>0&&hasNewAdmin){return;}
  users=[
    {id:uid(),name:'Admin',email:'admin',password:hash('admin123'),dob:'1985-06-15',gender:'male',role:'admin'},
    {id:uid(),name:'Andi Pratama',email:'andi@email.com',password:hash('andi123'),dob:'2000-03-10',gender:'male',role:'user'},
    {id:uid(),name:'Sari Dewi',email:'sari@email.com',password:hash('sari123'),dob:'1999-07-22',gender:'female',role:'user'},
    {id:uid(),name:'Rizky Ramadhan',email:'rizky@email.com',password:hash('rizky123'),dob:'2001-11-05',gender:'male',role:'user'}
  ];
  DB.setUsers(users);
  var aids=users.filter(function(u){return u.role==='user'}).map(function(u){return u.id});
  var logs=[];
  for(var ai=0;ai<aids.length;ai++){
    var aid=aids[ai];
    for(var i=6;i>=0;i--){
      var d=new Date();d.setDate(d.getDate()-i);
      var rpe=3+Math.floor(Math.random()*6);
      var dur=30+Math.floor(Math.random()*60);
      var hrS=60+Math.floor(Math.random()*20);
      var hrH=hrS+20+Math.floor(Math.random()*40);
      var perut=70+Math.floor(Math.random()*25);
      var pinggul=85+Math.floor(Math.random()*15);
      var u=users.find(function(x){return x.id===aid});
      logs.push({id:uid(),user_id:aid,tanggal:d.toISOString().split('T')[0],
        tb:u.gender==='male'?172+Math.floor(Math.random()*8):160+Math.floor(Math.random()*6),
        bb:u.gender==='male'?65+Math.floor(Math.random()*20):50+Math.floor(Math.random()*15),
        rpe:rpe,durasi:dur,hr_s:hrS,hr_h:hrH,perut:perut,pinggul:pinggul,whr:calcWHR(perut,pinggul),
        doms:1+Math.floor(Math.random()*5),stres:1+Math.floor(Math.random()*5),
        tidur:2+Math.floor(Math.random()*4),mood:2+Math.floor(Math.random()*4),motivasi:2+Math.floor(Math.random()*4),
        catatan:i===0?'Sesi latihan intens':''
      });
    }
  }
  DB.setLogs(logs);
}
seedDemo();
var curPage='login',curUser=null,selUserId=null,charts={};
function nav(p,d){d=d||{};curPage=p;if(d.userId)selUserId=d.userId;render()}
// ===== RENDER =====
function render(){
  var app=document.getElementById('app');
  var keys=Object.keys(charts);for(var i=0;i<keys.length;i++){charts[keys[i]].destroy()}charts={};
  if(!curUser){app.innerHTML=pgLogin();bindLogin()}
  else if(curUser.role==='admin'){app.innerHTML=pgAdminDash();bindAdmin()}
  else{app.innerHTML=pgUserDash();bindUser()}
}
// ===== LOGIN =====
function pgLogin(){
  if(curPage==='register')return pgRegister();
  return '<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-slate-50">'+
  '<div class="w-full max-w-md fade-in"><div class="text-center mb-8">'+
  '<div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4"><span class="text-white font-bold text-2xl">FT</span></div>'+
  '<h1 class="text-2xl font-bold text-gray-900">FitTracker</h1><p class="text-gray-500 mt-1 text-sm">Monitoring & Kesiapan Latihan</p></div>'+
  '<div class="card"><h2 class="text-lg font-semibold mb-5">Masuk</h2>'+
  '<div id="loginErr" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm"></div>'+
  '<form id="loginF" class="space-y-4">'+
  '<div><label class="form-label">Email / Username</label><input type="text" id="lEmail" class="form-input" value="admin" required></div>'+
  '<div><label class="form-label">Password</label><div class="relative"><input type="password" id="lPass" class="form-input" value="admin123" required>'+
  '<button type="button" onclick="tp(\'lPass\',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">&#128065;</button></div></div>'+
  '<button type="submit" class="btn btn-primary w-full">Masuk</button></form>'+
  '<p class="mt-5 text-center text-sm text-gray-500">Belum punya akun? <a href="#" onclick="nav(\'register\');return false" class="text-blue-600 font-semibold hover:underline">Daftar</a></p>'+
  '</div></div></div>';
}
function pgRegister(){
  return '<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-slate-50">'+
  '<div class="w-full max-w-md fade-in"><div class="text-center mb-6">'+
  '<div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4"><span class="text-white font-bold text-2xl">FT</span></div>'+
  '<h1 class="text-2xl font-bold text-gray-900">Buat Akun Baru</h1></div>'+
  '<div class="card"><a href="#" onclick="curPage=\'login\';render();return false" class="text-sm text-gray-500 hover:text-blue-600 mb-3 inline-block">&larr; Kembali</a>'+
  '<div id="regErr" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm"></div>'+
  '<form id="regF" class="space-y-3">'+
  '<div><label class="form-label">Nama Lengkap</label><input type="text" id="rName" class="form-input" required></div>'+
  '<div><label class="form-label">Email</label><input type="email" id="rEmail" class="form-input" required></div>'+
  '<div class="grid grid-cols-2 gap-3">'+
  '<div><label class="form-label">Tanggal Lahir</label><input type="date" id="rDob" class="form-input" required></div>'+
  '<div><label class="form-label">Jenis Kelamin</label><select id="rGender" class="form-input" required><option value="">Pilih</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select></div></div>'+
  '<div><label class="form-label">Password</label><div class="relative"><input type="password" id="rPass" class="form-input" minlength="6" required placeholder="Minimal 6 karakter">'+
  '<button type="button" onclick="tp(\'rPass\',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">&#128065;</button></div></div>'+
  '<div><label class="form-label">Konfirmasi Password</label><div class="relative"><input type="password" id="rPass2" class="form-input" required>'+
  '<button type="button" onclick="tp(\'rPass2\',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">&#128065;</button></div></div>'+
  '<button type="submit" class="btn btn-primary w-full">Daftar</button></form>'+
  '<p class="mt-4 text-center text-sm text-gray-500">Sudah punya akun? <a href="#" onclick="curPage=\'login\';render();return false" class="text-blue-600 font-semibold hover:underline">Masuk</a></p></div></div></div>';
}
function bindLogin(){
  var lf=document.getElementById('loginF');
  if(lf)lf.addEventListener('submit',function(e){
    e.preventDefault();var email=document.getElementById('lEmail').value.trim(),pass=document.getElementById('lPass').value;
    var u=DB.getUsers().find(function(x){return(x.email===email||x.name===email||x.email.toLowerCase()===email.toLowerCase()||x.name.toLowerCase()===email.toLowerCase())&&x.password===hash(pass)});
    if(!u){document.getElementById('loginErr').textContent='Email atau password salah.';document.getElementById('loginErr').classList.remove('hidden');return}
    curUser=u;DB.setSession({id:u.id});curPage='dashboard';render();toast('Selamat datang, '+u.name+'!');
  });
  var rf=document.getElementById('regF');
  if(rf)rf.addEventListener('submit',function(e){
    e.preventDefault();var n=document.getElementById('rName').value.trim(),em=document.getElementById('rEmail').value.trim(),
    dob=document.getElementById('rDob').value,g=document.getElementById('rGender').value,
    p=document.getElementById('rPass').value,p2=document.getElementById('rPass2').value;
    if(p.length<6){document.getElementById('regErr').textContent='Password minimal 6 karakter.';document.getElementById('regErr').classList.remove('hidden');return}
    if(p!==p2){document.getElementById('regErr').textContent='Password tidak cocok.';document.getElementById('regErr').classList.remove('hidden');return}
    var users=DB.getUsers();if(users.find(function(x){return x.email===em})){
      document.getElementById('regErr').textContent='Email sudah terdaftar.';document.getElementById('regErr').classList.remove('hidden');return}
    users.push({id:uid(),name:n,email:em,password:hash(p),dob:dob,gender:g,role:'user'});DB.setUsers(users);
    toast('Akun dibuat! Silakan masuk.');curPage='login';render();
  });
}
function tp(id,b){var i=document.getElementById(id);if(i.type==='password'){i.type='text';b.textContent='*'}else{i.type='password';b.textContent='\u{1F441}'}}
// ===== NAVBAR =====
function navBar(){
  var a=curUser&&curUser.role==='admin';
  return '<nav class="bg-white border-b border-gray-200 sticky top-0 z-50"><div class="max-w-6xl mx-auto px-4 sm:px-6"><div class="flex justify-between h-14">'+
  '<div class="flex items-center space-x-6"><a href="#" onclick="nav(\'dashboard\');return false" class="flex items-center space-x-2">'+
  '<div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center"><span class="text-white font-bold text-sm">FT</span></div>'+
  '<span class="font-bold text-gray-900 hidden sm:block">FitTracker</span></a>'+
  '<div class="flex items-center space-x-1">'+
  '<a href="#" onclick="nav(\'dashboard\');return false" class="nav-link '+(curPage==='dashboard'?'active':'')+'">Dashboard</a>'+
  (a?'<a href="#" onclick="nav(\'input\');return false" class="nav-link '+(curPage==='input'?'active':'')+'">Input Latihan</a><a href="#" onclick="nav(\'athletes\');return false" class="nav-link '+(curPage==='athletes'?'active':'')+'">User</a>'
  :'<a href="#" onclick="nav(\'logbook\');return false" class="nav-link '+(curPage==='logbook'?'active':'')+'">Logbook</a><a href="#" onclick="nav(\'recs\');return false" class="nav-link '+(curPage==='recs'?'active':'')+'">Rekomendasi</a>')+
  '</div></div>'+
  '<div class="flex items-center space-x-3"><span class="text-sm text-gray-600 hidden sm:block">'+curUser.name+'</span>'+
  '<span class="badge '+(a?'badge-red':'badge-green')+'">'+(a?'Admin':'User')+'</span>'+
  '<button onclick="DB.clearSession();curUser=null;curPage=\'login\';render()" class="text-sm text-gray-500 hover:text-red-600">Logout</button></div>'+
  '</div></div></nav>';
}
// ===== ADMIN DASHBOARD =====
function pgAdminDash(){
  if(curPage==='input')return navBar()+pgInputForm();
  if(curPage==='athletes')return navBar()+pgAthletes();
  if(curPage==='view-user')return navBar()+pgViewUser();
  var users=DB.getUsers().filter(function(u){return u.role==='user'}),logs=DB.getLogs(),today=new Date().toISOString().split('T')[0];
  var tl=logs.filter(function(l){return l.tanggal===today});
  return navBar()+'<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Dashboard Admin</h1><p class="text-gray-500 text-sm mt-1">Pantau kesiapan seluruh user</p></div>'+
  '<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-blue-600">'+users.length+'</div><div class="text-xs text-gray-500 mt-1">Total User</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-green-600">'+tl.length+'</div><div class="text-xs text-gray-500 mt-1">Input Hari Ini</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-orange-500">'+(tl.length?(tl.reduce(function(s,l){return s+l.rpe},0)/tl.length).toFixed(1):'-')+'</div><div class="text-xs text-gray-500 mt-1">RPE Rata-rata</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-red-600">'+tl.filter(function(l){return fatigueScore(l).z==='red'}).length+'</div><div class="text-xs text-gray-500 mt-1">Red Zone</div></div></div>'+
  '<div class="card"><h2 class="text-lg font-semibold mb-4">Daftar User</h2>'+
  (users.length===0?'<p class="text-gray-400 text-sm">Belum ada user.</p>':buildAdminTable(users,logs,today))+
  '</div></main>';
}
function buildAdminTable(users,logs,today){
  var rows='';
  for(var i=0;i<users.length;i++){
    var u=users[i];
    var ul=logs.filter(function(l){return l.user_id===u.id});
    var tl=ul.find(function(l){return l.tanggal===today});
    var fs=tl?fatigueScore(tl):null;
    rows+='<tr class="border-b table-row"><td class="py-3 font-medium">'+u.name+'</td><td class="py-3 text-gray-500">'+age(u.dob)+' th</td>'+
    '<td class="py-3">'+(tl?'<span class="badge badge-green">\u2713</span>':'<span class="badge badge-yellow">Belum</span>')+'</td>'+
    '<td class="py-3 font-semibold">'+(tl?tl.rpe:'-')+'</td>'+
    '<td class="py-3">'+(fs?'<span class="badge '+fs.c+'">'+fs.l+'</span>':'-')+'</td>'+
    '<td class="py-3"><button class="btn btn-sm btn-secondary" onclick="nav(\'view-user\',{userId:\''+u.id+'\'})">Lihat</button> '+
    '<button class="btn btn-sm btn-primary" onclick="nav(\'input\',{userId:\''+u.id+'\'})">Input</button></td></tr>';
  }
  return '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b">'+
  '<th class="pb-3 font-medium">Nama</th><th class="pb-3 font-medium">Usia</th><th class="pb-3 font-medium">Hari Ini</th><th class="pb-3 font-medium">RPE</th><th class="pb-3 font-medium">Fatigue</th><th class="pb-3 font-medium">Aksi</th></tr></thead><tbody>'+
  rows+'</tbody></table></div>';
}
// ===== INPUT FORM =====
function pgInputForm(){
  var users=DB.getUsers().filter(function(u){return u.role==='user'});
  var opts='';
  for(var i=0;i<users.length;i++){
    opts+='<option value="'+users[i].id+'" '+(users[i].id===selUserId?'selected':'')+'>'+users[i].name+'</option>';
  }
  var today=new Date().toISOString().split('T')[0];
  return '<main class="max-w-2xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Input Hasil Latihan</h1><p class="text-gray-500 text-sm mt-1">Masukkan data latihan untuk user</p></div>'+
  '<form id="inputF" class="space-y-4">'+
  '<div class="card"><h3 class="font-semibold text-gray-700 mb-3">Data Diri User</h3>'+
  '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3"><div class="sm:col-span-2"><label class="form-label">Pilih User</label><select id="iUser" class="form-input" required onchange="previewWHR()"><option value="">-- Pilih User --</option>'+opts+'</select></div>'+
  '<div><label class="form-label">Tanggal</label><input type="date" id="iDate" class="form-input" value="'+today+'" required></div></div>'+
  '<div class="grid grid-cols-2 gap-3 mt-3"><div><label class="form-label">Tinggi Badan (cm)</label><input type="number" id="iTb" class="form-input" step="0.1" min="100" max="250" required></div>'+
  '<div><label class="form-label">Berat Badan (kg)</label><input type="number" id="iBb" class="form-input" step="0.1" min="30" max="250" required></div></div></div>'+
  '<div class="card"><h3 class="font-semibold text-gray-700 mb-3">Logbook Latihan</h3>'+
  '<div class="grid grid-cols-2 gap-3">'+
  '<div><label class="form-label">RPE Latihan (0-10)</label><input type="number" id="iRpe" class="form-input" min="0" max="10" step="0.5" required></div>'+
  '<div><label class="form-label">Durasi (menit)</label><input type="number" id="iDur" class="form-input" min="1" max="300" required></div>'+
  '<div><label class="form-label">HR Sebelum (bpm)</label><input type="number" id="iHrs" class="form-input" min="30" max="220" required></div>'+
  '<div><label class="form-label">HR Sesudah (bpm)</label><input type="number" id="iHrh" class="form-input" min="40" max="250" required></div>'+
  '<div><label class="form-label">Lingkar Perut (cm)</label><input type="number" id="iPerut" class="form-input" step="0.1" min="40" max="200" required oninput="previewWHR()"></div>'+
  '<div><label class="form-label">Lingkar Pinggul (cm)</label><input type="number" id="iPinggul" class="form-input" step="0.1" min="40" max="200" required oninput="previewWHR()"></div></div>'+
  '<div class="mt-3 p-3 bg-gray-50 rounded-lg text-sm">WHR: <span class="font-bold" id="whrVal">-</span> <span id="whrCat"></span></div></div>'+
  '<div class="card"><h3 class="font-semibold text-gray-700 mb-3">Evaluasi Pemulihan & Stres</h3>'+
  '<div class="grid grid-cols-2 sm:grid-cols-3 gap-3">'+
  '<div><label class="form-label">DOMS (1-5)</label><select id="iDoms" class="form-input" required><option value="">--</option><option value="1">1 - Sangat Ringan</option><option value="2">2 - Ringan</option><option value="3">3 - Sedang</option><option value="4">4 - Berat</option><option value="5">5 - Sangat Berat</option></select></div>'+
  '<div><label class="form-label">Stres (1-5)</label><select id="iStres" class="form-input" required><option value="">--</option><option value="1">1 - Sangat Rendah</option><option value="2">2 - Rendah</option><option value="3">3 - Sedang</option><option value="4">4 - Tinggi</option><option value="5">5 - Sangat Tinggi</option></select></div>'+
  '<div><label class="form-label">Tidur (1-5)</label><select id="iTidur" class="form-input" required><option value="">--</option><option value="1">1 - Sangat Buruk</option><option value="2">2 - Buruk</option><option value="3">3 - Cukup</option><option value="4">4 - Baik</option><option value="5">5 - Sangat Baik</option></select></div>'+
  '<div><label class="form-label">Mood (1-5)</label><select id="iMood" class="form-input" required><option value="">--</option><option value="1">1 - Sangat Buruk</option><option value="2">2 - Buruk</option><option value="3">3 - Netral</option><option value="4">4 - Baik</option><option value="5">5 - Sangat Baik</option></select></div>'+
  '<div><label class="form-label">Motivasi (1-5)</label><select id="iMoti" class="form-input" required><option value="">--</option><option value="1">1 - Sangat Rendah</option><option value="2">2 - Rendah</option><option value="3">3 - Sedang</option><option value="4">4 - Tinggi</option><option value="5">5 - Sangat Tinggi</option></select></div></div>'+
  '<div class="mt-3"><label class="form-label">Catatan (opsional)</label><textarea id="iCat" class="form-input" rows="2" placeholder="Kondisi hari ini..."></textarea></div></div>'+
  '<div class="flex justify-end space-x-3"><a href="#" onclick="nav(\'dashboard\');return false" class="btn btn-secondary">Batal</a>'+
  '<button type="submit" class="btn btn-primary">Simpan Data</button></div></form></main>';
}
function previewWHR(){
  var p=parseFloat(document.getElementById('iPerut').value);
  var g=parseFloat(document.getElementById('iPinggul').value);
  if(p&&g&&g>0){var w=(p/g).toFixed(3);document.getElementById('whrVal').textContent=w;
  var uid_=document.getElementById('iUser').value;
  var user=DB.getUsers().find(function(u){return u.id===uid_});
  var sex=user?user.gender:'male';
  var wc=whrCat(parseFloat(w),sex);
  document.getElementById('whrCat').innerHTML='<span class="'+wc.c+' font-semibold">'+wc.l+'</span>';}
  else{document.getElementById('whrVal').textContent='-';document.getElementById('whrCat').innerHTML='';}
}
function bindAdmin(){
  if(curPage==='view-user'){
    var logs=DB.getLogs().filter(function(l){return l.user_id===selUserId}).sort(function(a,b){return a.tanggal.localeCompare(b.tanggal)});
    if(logs.length){
      var labels=logs.map(function(l){return l.tanggal.substring(5)});
      var admChart=document.getElementById('admChart');
      if(admChart){
        var ctx=admChart.getContext('2d');
        new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[
          {label:'RPE',data:logs.map(function(l){return l.rpe}),backgroundColor:'rgba(37,99,235,0.7)',yAxisID:'y'},
          {label:'Fatigue',data:logs.map(function(l){return parseFloat(fatigueScore(l).s)}),backgroundColor:'rgba(239,68,68,0.7)',yAxisID:'y1'}
        ]},options:{responsive:true,scales:{y:{beginAtZero:true,max:10,position:'left',title:{display:true,text:'RPE'}},y1:{beginAtZero:true,max:10,position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Fatigue'}}}}});
      }
    }
  }
  var f=document.getElementById('inputF');
  if(f)f.addEventListener('submit',function(e){
    e.preventDefault();
    var uid_=document.getElementById('iUser').value;
    if(!uid_){toast('Pilih user terlebih dahulu!','error');return}
    var user=DB.getUsers().find(function(u){return u.id===uid_});
    var perut=parseFloat(document.getElementById('iPerut').value);
    var pinggul=parseFloat(document.getElementById('iPinggul').value);
    var log={id:uid(),user_id:uid_,tanggal:document.getElementById('iDate').value,
      tb:parseFloat(document.getElementById('iTb').value),bb:parseFloat(document.getElementById('iBb').value),
      rpe:parseFloat(document.getElementById('iRpe').value),durasi:parseInt(document.getElementById('iDur').value),
      hr_s:parseInt(document.getElementById('iHrs').value),hr_h:parseInt(document.getElementById('iHrh').value),
      perut:perut,pinggul:pinggul,whr:calcWHR(perut,pinggul),
      doms:parseInt(document.getElementById('iDoms').value),stres:parseInt(document.getElementById('iStres').value),
      tidur:parseInt(document.getElementById('iTidur').value),mood:parseInt(document.getElementById('iMood').value),
      motivasi:parseInt(document.getElementById('iMoti').value),catatan:document.getElementById('iCat').value
    };
    var logs=DB.getLogs();logs.push(log);DB.setLogs(logs);
    toast('Data latihan tersimpan untuk '+user.name+'!');
    nav('dashboard');
  });
}
// ===== ATHLETES LIST =====
function pgAthletes(){
  var users=DB.getUsers().filter(function(u){return u.role==='user'});
  var logs=DB.getLogs(),today=new Date().toISOString().split('T')[0];
  var cards='';
  for(var i=0;i<users.length;i++){
    var u=users[i];var ul=logs.filter(function(l){return l.user_id===u.id});
    var tl=ul.find(function(l){return l.tanggal===today});var fs=tl?fatigueScore(tl):null;
    cards+='<div class="card fade-in"><div class="flex items-center justify-between mb-3"><div><div class="font-semibold">'+u.name+'</div>'+
    '<div class="text-xs text-gray-500">'+age(u.dob)+' tahun &middot; '+(u.gender==='male'?'L':'P')+'</div></div>'+
    (fs?'<span class="badge '+fs.c+'">'+fs.l+'</span>':'<span class="badge badge-yellow">Belum input</span>')+'</div>'+
    '<div class="grid grid-cols-3 gap-2 text-center text-xs mb-3">'+
    '<div class="bg-gray-50 rounded-lg p-2"><div class="font-bold text-sm">'+ul.length+'</div><div class="text-gray-500">Sesi</div></div>'+
    '<div class="bg-gray-50 rounded-lg p-2"><div class="font-bold text-sm">'+(tl?tl.rpe:'-')+'</div><div class="text-gray-500">RPE</div></div>'+
    '<div class="bg-gray-50 rounded-lg p-2"><div class="font-bold text-sm">'+(tl?tl.durasi+'m':'-')+'</div><div class="text-gray-500">Durasi</div></div></div>'+
    '<div class="flex space-x-2"><button class="btn btn-sm btn-primary flex-1" onclick="nav(\'input\',{userId:\''+u.id+'\'})">Input</button>'+
    '<button class="btn btn-sm btn-secondary flex-1" onclick="nav(\'view-user\',{userId:\''+u.id+'\'})">Lihat Data</button></div></div>';
  }
  return '<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Daftar User</h1><p class="text-gray-500 text-sm">'+users.length+' user terdaftar</p></div>'+
  (users.length===0?'<div class="card text-center py-12 text-gray-400">Belum ada user.</div>':'<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">'+cards+'</div>')+
  '</main>';
}
// ===== VIEW USER =====
function pgViewUser(){
  if(!selUserId)return '<div class="p-8 text-center text-gray-500">Pilih user.</div>';
  var user=DB.getUsers().find(function(u){return u.id===selUserId});
  if(!user)return '<div class="p-8 text-center text-gray-500">User tidak ditemukan.</div>';
  var logs=DB.getLogs().filter(function(l){return l.user_id===selUserId}).sort(function(a,b){return b.tanggal.localeCompare(a.tanggal)});
  if(logs.length===0)return '<main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 fade-in"><div class="flex items-center justify-between mb-6"><div><h1 class="text-2xl font-bold text-gray-900">'+user.name+'</h1></div><button class="btn btn-sm btn-secondary" onclick="nav(\'athletes\')">← Kembali</button></div><div class="card text-center py-12 text-gray-400">Belum ada data latihan.</div></main>';
  var fs=fatigueScore(logs[0]);
  var rows='';
  for(var i=0;i<Math.min(logs.length,14);i++){
    var l=logs[i];var f=fatigueScore(l);
    rows+='<tr class="border-b table-row text-xs"><td class="py-2">'+fmt(l.tanggal)+'</td><td class="py-2 font-semibold">'+l.rpe+'</td><td class="py-2">'+l.durasi+'m</td><td class="py-2">'+l.hr_s+'→'+l.hr_h+'</td><td class="py-2">'+l.whr+'</td><td class="py-2">'+l.doms+'/5</td><td class="py-2"><span class="badge '+f.c+'">'+f.l+'</span></td></tr>';
  }
  return '<main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 fade-in"><div class="flex items-center justify-between mb-6"><div><h1 class="text-2xl font-bold text-gray-900">'+user.name+'</h1><p class="text-gray-500 text-sm">'+age(user.dob)+' tahun &middot; '+(user.gender==='male'?'Laki-laki':'Perempuan')+'</p></div>'+
  '<div class="flex space-x-2"><button class="btn btn-sm btn-primary" onclick="nav(\'input\',{userId:\''+user.id+'\'})">+ Input Baru</button><button class="btn btn-sm btn-secondary" onclick="nav(\'athletes\')">← Kembali</button></div></div>'+
  '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">'+
  '<div class="card-sm stat-card"><div class="text-lg font-bold">'+logs.length+'</div><div class="text-xs text-gray-500">Total Sesi</div></div>'+
  '<div class="card-sm stat-card"><div class="text-lg font-bold text-blue-600">'+(logs.reduce(function(s,l){return s+l.rpe},0)/logs.length).toFixed(1)+'</div><div class="text-xs text-gray-500">RPE Avg</div></div>'+
  '<div class="card-sm stat-card"><div class="text-lg font-bold text-orange-500">'+logs[0].whr+'</div><div class="text-xs text-gray-500">WHR</div></div>'+
  '<div class="card-sm stat-card"><div class="text-lg font-bold '+(fs.z==='red'?'text-red-600':fs.z==='yellow'?'text-yellow-600':'text-green-600')+'">'+fs.l+'</div><div class="text-xs text-gray-500">Fatigue</div></div></div>'+
  '<div class="card mb-4"><h3 class="font-semibold mb-3">Grafik RPE & Fatigue</h3><canvas id="admChart" height="80"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">Riwayat Latihan</h3><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b text-xs"><th class="pb-2">Tanggal</th><th class="pb-2">RPE</th><th class="pb-2">Durasi</th><th class="pb-2">HR</th><th class="pb-2">WHR</th><th class="pb-2">DOMS</th><th class="pb-2">Fatigue</th></tr></thead><tbody>'+
  rows+'</tbody></table></div></div></main>';
}
// ===== USER DASHBOARD =====
function pgUserDash(){
  if(curPage==='logbook')return navBar()+pgLogbook();
  if(curPage==='recs')return navBar()+pgRecs();
  var logs=DB.getLogs().filter(function(l){return l.user_id===curUser.id}).sort(function(a,b){return b.tanggal.localeCompare(a.tanggal)});
  var latest=logs[0];var fs=latest?fatigueScore(latest):null;
  if(!latest)return '<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in"><div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Dashboard</h1><p class="text-gray-500 text-sm">Selamat datang, '+curUser.name+'</p></div><div class="card text-center py-12"><p class="text-gray-400">Belum ada data latihan.</p></div></main>';
  return navBar()+'<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Dashboard</h1><p class="text-gray-500 text-sm">Selamat datang, '+curUser.name+'</p></div>'+
  '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-blue-600">'+latest.rpe+'</div><div class="text-xs text-gray-500">RPE Terakhir</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-green-600">'+latest.durasi+'m</div><div class="text-xs text-gray-500">Durasi</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold text-orange-500">'+latest.whr+'</div><div class="text-xs text-gray-500">WHR</div></div>'+
  '<div class="card-sm stat-card"><div class="text-2xl font-bold '+(fs.z==='red'?'text-red-600':fs.z==='yellow'?'text-yellow-600':'text-green-600')+'">'+fs.l+'</div><div class="text-xs text-gray-500">Fatigue</div></div></div>'+
  (latest.whr&&latest.whr!=='-'?function(){var wc=whrCat(latest.whr,curUser.gender);return '<div class="card mb-6 p-4"><div class="flex items-center gap-3 mb-2"><span class="text-sm font-semibold text-gray-700">Hasil Waist-to-Hip Ratio</span><span class="badge '+wc.c+'">'+wc.l+'</span></div><div class="grid grid-cols-2 gap-4 text-sm"><div><span class="text-gray-500">Lingkar Perut:</span> <span class="font-semibold">'+latest.perut+' cm</span></div><div><span class="text-gray-500">Lingkar Pinggul:</span> <span class="font-semibold">'+latest.pinggul+' cm</span></div><div><span class="text-gray-500">Rasio (WHR):</span> <span class="font-semibold">'+latest.whr+'</span></div><div><span class="text-gray-500">Batas Normal ('+(curUser.gender==='male'?'Pria':'Wanita')+'): &lt; '+(curUser.gender==='male'?'0.90':'0.80')+'</span></div></div><p class="text-xs text-gray-500 mt-2 italic">'+wc.d+'</p></div>';}():'')+''+
  '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">'+
  '<div class="card"><h3 class="font-semibold mb-3">Tren RPE (7 Hari)</h3><canvas id="dashC1" height="100"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">HR Sebelum vs Sesudah</h3><canvas id="dashC2" height="100"></canvas></div></div>'+
  '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">'+
  '<div class="card"><h3 class="font-semibold mb-3">Tren WHR</h3><canvas id="dashC3" height="100"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">Fatigue Score</h3><canvas id="dashC4" height="100"></canvas></div></div></main>';
}
// ===== USER LOGBOOK =====
function pgLogbook(){
  var logs=DB.getLogs().filter(function(l){return l.user_id===curUser.id}).sort(function(a,b){return b.tanggal.localeCompare(a.tanggal)});
  if(logs.length===0)return '<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in"><div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Logbook</h1></div><div class="card text-center py-12 text-gray-400">Belum ada data latihan.</div></main>';
  var rows='';
  for(var i=0;i<logs.length;i++){
    var l=logs[i];var f=fatigueScore(l);
    rows+='<tr class="border-b table-row text-xs"><td class="py-2">'+fmt(l.tanggal)+'</td><td class="py-2 font-semibold">'+l.rpe+'</td><td class="py-2">'+l.durasi+'m</td><td class="py-2">'+l.hr_s+'→'+l.hr_h+'</td><td class="py-2">'+l.whr+'</td><td class="py-2">'+l.doms+'/5</td><td class="py-2">'+l.stres+'/5</td><td class="py-2"><span class="badge '+f.c+'">'+f.l+'</span></td></tr>';
  }
  return '<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Logbook Latihan</h1><p class="text-gray-500 text-sm">Riwayat & grafik progres</p></div>'+
  '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">'+
  '<div class="card"><h3 class="font-semibold mb-3">Beban Latihan (RPE x Durasi)</h3><canvas id="logC1" height="100"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">HR Sebelum vs Sesudah</h3><canvas id="logC2" height="100"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">Tren WHR</h3><canvas id="logC3" height="100"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">Skor DOMS & Stres</h3><canvas id="logC4" height="100"></canvas></div></div>'+
  '<div class="card mb-4"><h3 class="font-semibold mb-3">Indikator Tingkat Kelelahan</h3><canvas id="logC5" height="60"></canvas></div>'+
  '<div class="card"><h3 class="font-semibold mb-3">Riwayat Sesi</h3><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b text-xs"><th class="pb-2">Tanggal</th><th class="pb-2">RPE</th><th class="pb-2">Durasi</th><th class="pb-2">HR</th><th class="pb-2">WHR</th><th class="pb-2">DOMS</th><th class="pb-2">Stres</th><th class="pb-2">Fatigue</th></tr></thead><tbody>'+
  rows+'</tbody></table></div></div></main>';
}
// ===== RECOMMENDATIONS =====
function pgRecs(){
  var logs=DB.getLogs().filter(function(l){return l.user_id===curUser.id}).sort(function(a,b){return b.tanggal.localeCompare(a.tanggal)});
  var latest=logs[0];var fs=latest?fatigueScore(latest):null;
  var zone=fs?fs.z:'green';
  var exRecs=[],dietRecs=[];
  if(zone==='red'){exRecs=['Active recovery ringan: jalan kaki 20-30 menit atau stretching ringan.','Hindari latihan berat hari ini. Istirahat total jika kelelahan ekstrem.','Konsultasikan dengan pelatih/dokter jika kondisi berlanjut.'];}
  else if(zone==='yellow'){exRecs=['Turunkan intensitas ke zona sedang. Prioritas mobility & teknik.','Volume latihan diturunkan 20-30%. Fokus pemulihan aktif (yoga, stretching).'];}
  else{exRecs=['Kondisi optimal! Lakukan aerobik 150-300 menit/minggu.','Tambahkan latihan penguatan otot minimal 2x/minggu.'];}
  if(latest&&latest.whr&&latest.whr>0.90){exRecs.push('WHR tinggi -> tambahkan kardio rutin 20-30 menit untuk kurangi lemak visceral.');}
  if(latest&&latest.doms&&latest.doms>=4){exRecs.push('DOMS tinggi -> fokus recovery: foam rolling, stretching, tidur cukup.');}
  dietRecs=['Konsumsi buah & sayur minimal 400 g/hari.','Batasi gula tambahan <5% total energi (<25g/hari) dan garam <5 g/hari.','Utamakan lemak tak jenuh (ikan, alpukat, kacang-kacangan). Hindari lemak trans.','Batasi lemak total <30% dari total energi harian.'];
  if(latest&&latest.bb&&latest.tb){
    var bmi=(latest.bb/((latest.tb/100)*(latest.tb/100))).toFixed(1);
    if(bmi>=25)dietRecs.push('BMI '+bmi+' (overweight) -> terapkan defisit kalori ringan 300-500 kkal/hari.');
  }
  var exHtml='';for(var i=0;i<exRecs.length;i++)exHtml+='<li class="flex items-start text-sm text-gray-700 mb-2"><span class="text-blue-500 mr-2 mt-0.5">&#8226;</span>'+exRecs[i]+'</li>';
  var dietHtml='';for(var i=0;i<dietRecs.length;i++)dietHtml+='<li class="flex items-start text-sm text-gray-700 mb-2"><span class="text-green-500 mr-2 mt-0.5">&#8226;</span>'+dietRecs[i]+'</li>';
  return '<main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 fade-in">'+
  '<div class="mb-6"><h1 class="text-2xl font-bold text-gray-900">Rekomendasi Hari Ini</h1><p class="text-gray-500 text-sm">Berdasarkan status kelelahan & komposisi tubuh Anda</p></div>'+
  (fs?'<div class="mb-4 p-4 rounded-xl '+(zone==='red'?'bg-red-50 border border-red-200':zone==='yellow'?'bg-yellow-50 border border-yellow-200':'bg-green-50 border border-green-200')+'"><span class="font-semibold">Status Kelelahan: </span><span class="badge '+fs.c+'">'+fs.l+'</span> (Skor: '+fs.s+')</div>':'')+
  '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">'+
  '<div class="card"><h3 class="font-semibold text-blue-700 mb-3">Rekomendasi Aktivitas Fisik</h3><ul class="space-y-1">'+exHtml+'</ul></div>'+
  '<div class="card"><h3 class="font-semibold text-green-700 mb-3">Rekomendasi Pola Makan</h3><ul class="space-y-1">'+dietHtml+'</ul></div></div>'+
  '<div class="p-4 bg-gray-50 rounded-xl text-xs text-gray-500 italic">* Rekomendasi berbasis WHO Guidelines on Physical Activity & Healthy Diet. Bukan pengganti konsultasi ahli gizi/dokter.</div></main>';
}
function bindUser(){
  var logs=DB.getLogs().filter(function(l){return l.user_id===curUser.id}).sort(function(a,b){return a.tanggal.localeCompare(b.tanggal)});
  var rev=logs.slice(-7);
  if(curPage==='dashboard'){
    setTimeout(function(){
      makeDashCharts(rev);
    },100);
  }else if(curPage==='logbook'){
    setTimeout(function(){
      makeLogCharts(logs);
    },100);
  }
}
// ===== CHARTS =====
function makeDashCharts(data){
  if(!data.length)return;
  var labels=data.map(function(l){return l.tanggal.substring(5)});
  charts.d1=new Chart(document.getElementById('dashC1'),{type:'line',data:{labels:labels,datasets:[{label:'RPE',data:data.map(function(l){return l.rpe}),borderColor:'#2563eb',tension:0.3,fill:false}]},options:{responsive:true,plugins:{legend:{display:false}}}});
  charts.d2=new Chart(document.getElementById('dashC2'),{type:'line',data:{labels:labels,datasets:[{label:'HR Sebelum',data:data.map(function(l){return l.hr_s}),borderColor:'#22c55e',tension:0.3},{label:'HR Sesudah',data:data.map(function(l){return l.hr_h}),borderColor:'#ef4444',tension:0.3}]},options:{responsive:true}});
  charts.d3=new Chart(document.getElementById('dashC3'),{type:'line',data:{labels:labels,datasets:[{label:'WHR',data:data.map(function(l){return parseFloat(l.whr)}),borderColor:'#f59e0b',tension:0.3,fill:false}]},options:{responsive:true,plugins:{legend:{display:false}}}});
  charts.d4=new Chart(document.getElementById('dashC4'),{type:'bar',data:{labels:labels,datasets:[{label:'Fatigue',data:data.map(function(l){return parseFloat(fatigueScore(l).s)}),backgroundColor:data.map(function(l){var z=fatigueScore(l).z;return z==='red'?'#ef4444':z==='yellow'?'#eab308':z==='blue'?'#3b82f6':'#22c55e'})}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:10}}}});
}
function makeLogCharts(data){
  if(!data.length)return;
  var labels=data.map(function(l){return l.tanggal.substring(5)});
  charts.l1=new Chart(document.getElementById('logC1'),{type:'bar',data:{labels:labels,datasets:[{label:'Load (RPE x Dur)',data:data.map(function(l){return (l.rpe*l.durasi/60).toFixed(1)}),backgroundColor:'#3b82f6'}]},options:{responsive:true,plugins:{legend:{display:false}}}});
  charts.l2=new Chart(document.getElementById('logC2'),{type:'line',data:{labels:labels,datasets:[{label:'HR Sebelum',data:data.map(function(l){return l.hr_s}),borderColor:'#22c55e',tension:0.3},{label:'HR Sesudah',data:data.map(function(l){return l.hr_h}),borderColor:'#ef4444',tension:0.3}]},options:{responsive:true}});
  charts.l3=new Chart(document.getElementById('logC3'),{type:'line',data:{labels:labels,datasets:[{label:'WHR',data:data.map(function(l){return parseFloat(l.whr)}),borderColor:'#f59e0b',tension:0.3,fill:false}]},options:{responsive:true,plugins:{legend:{display:false}}}});
  charts.l4=new Chart(document.getElementById('logC4'),{type:'line',data:{labels:labels,datasets:[{label:'DOMS',data:data.map(function(l){return l.doms}),borderColor:'#ef4444',tension:0.3},{label:'Stres',data:data.map(function(l){return l.stres}),borderColor:'#8b5cf6',tension:0.3}]},options:{responsive:true,scales:{y:{beginAtZero:true,max:5}}}});
  charts.l5=new Chart(document.getElementById('logC5'),{type:'bar',data:{labels:labels,datasets:[{label:'Fatigue Score',data:data.map(function(l){return parseFloat(fatigueScore(l).s)}),backgroundColor:data.map(function(l){var z=fatigueScore(l).z;return z==='red'?'#ef4444':z==='yellow'?'#eab308':z==='blue'?'#3b82f6':'#22c55e'})}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:10}}}});
}
// ===== INIT =====
seedDemo();render();
FS.init().then(function(){
  seedDemo();render();
}).catch(function(e){console.warn('Firestore init failed, using local data',e)});

