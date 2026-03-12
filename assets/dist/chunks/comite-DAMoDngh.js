import{c as s,g as o,p as n}from"./components-B7hyBG0k.js";import{j as i}from"./query-GzUKkhGG.js";/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const d=s("CircleX",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-react v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=s("MessageSquarePlus",[["path",{d:"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z",key:"1lielz"}],["path",{d:"M12 7v6",key:"lw1j43"}],["path",{d:"M9 10h6",key:"9gxzsh"}]]);function u({regions:e,value:r,onChange:t}){return i.jsxs("select",{value:r,onChange:a=>t(a.target.value),"aria-label":"Filtrar por region",style:{padding:"8px 14px",border:"1.5px solid var(--gnf-field-border)",borderRadius:"var(--gnf-radius)",fontSize:"0.9375rem",fontFamily:"var(--gnf-font-body)",background:"var(--gnf-white)",color:"var(--gnf-text)",cursor:"pointer"},children:[i.jsx("option",{value:"",children:"Todas las regiones"}),e.map(a=>i.jsx("option",{value:String(a.id),children:a.name},a.id))]})}const p={getDashboard(e){return o("/comite/dashboard",{year:e})},getCentros(e,r){return o("/comite/centros",{year:e,region:r})},getCentroDetail(e,r){return o(`/comite/centros/${e}`,{year:r})},validateCentro(e,r,t){return n(`/comite/centros/${e}/validate`,{year:r,...t})},addObservation(e,r,t){return n(`/comite/centros/${e}/observation`,{year:r,...t})},getHistorial(e){return o("/comite/historial",{year:e})}};export{d as C,g as M,u as R,p as c};
