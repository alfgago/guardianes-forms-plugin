import{c as r,j as s}from"./components-DQxeVk7U.js";import{j as o}from"./query-GzUKkhGG.js";/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const d=r("Bell",[["path",{d:"M10.268 21a2 2 0 0 0 3.464 0",key:"vwvbt9"}],["path",{d:"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326",key:"11g9vi"}]]);/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=r("RotateCcw",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]]),c=s(i=>({notifications:[],unreadCount:0,setNotifications:t=>i({notifications:t,unreadCount:t.filter(e=>!e.leido).length}),markRead:t=>i(e=>{const a=e.notifications.map(n=>n.id===t?{...n,leido:!0}:n);return{notifications:a,unreadCount:a.filter(n=>!n.leido).length}})}));function h({onClick:i}){const t=c(e=>e.unreadCount);return o.jsxs("button",{onClick:i,"aria-label":`Notificaciones (${t} sin leer)`,style:{position:"relative",display:"flex",alignItems:"center",justifyContent:"center",width:40,height:40,border:"none",background:"none",cursor:"pointer",borderRadius:"var(--gnf-radius-sm)",color:"var(--gnf-gray-600)"},children:[o.jsx(d,{size:20}),t>0&&o.jsx("span",{style:{position:"absolute",top:4,right:4,minWidth:16,height:16,display:"flex",alignItems:"center",justifyContent:"center",borderRadius:"var(--gnf-radius-full)",background:"var(--gnf-coral)",color:"var(--gnf-white)",fontSize:"0.625rem",fontWeight:700,padding:"0 4px"},children:t>9?"9+":t})]})}export{d as B,h as N,f as R,c as u};
