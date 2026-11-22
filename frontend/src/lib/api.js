const ORIGIN = (import.meta.env.VITE_API_ORIGIN || '').replace(/\/$/, '');
export const apiBase = `${ORIGIN}/api`;
export const CAMPUS_TIME_ZONE='Asia/Manila';
export function getToken(){return sessionStorage.getItem('spc_token');}
export function setToken(t){if(t)sessionStorage.setItem('spc_token',t);else sessionStorage.removeItem('spc_token');}
export async function api(path,{method='GET',body,signal,headers={}}={}){
  const isForm=body instanceof FormData;
  const response=await fetch(`${apiBase}${path}`,{method,signal,headers:{Accept:'application/json',...(!isForm && body!==undefined ? {'Content-Type':'application/json'} : {}),...(getToken()?{Authorization:`Bearer ${getToken()}`}:{}) ,...headers},body:body===undefined?undefined:(isForm?body:JSON.stringify(body))});
  const data=await response.json().catch(()=>({}));
  if(!response.ok){
    if(response.status===401)window.dispatchEvent(new Event('spc-unauthorized'));
    const errors=data.errors ? Object.values(data.errors).flat().join(' ') : '';
    throw new Error(errors || data.message || `Request failed (${response.status}).`);
  }
  return data;
}
export async function downloadCsv(path,filename){
  const response=await fetch(`${apiBase}${path}`,{headers:{Accept:'text/csv',...(getToken()?{Authorization:`Bearer ${getToken()}`}:{})}});
  if(!response.ok)throw new Error('Could not export RSVP list.');
  const url=URL.createObjectURL(await response.blob());
  const a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();a.remove();
  setTimeout(()=>URL.revokeObjectURL(url),2000);
}
export function campusDateParts(value){const d=new Date(value);if(Number.isNaN(d.getTime()))return null;const p=Object.fromEntries(new Intl.DateTimeFormat('en-PH',{timeZone:CAMPUS_TIME_ZONE,year:'numeric',month:'numeric',day:'numeric'}).formatToParts(d).map(x=>[x.type,x.value]));return {year:Number(p.year),month:Number(p.month),day:Number(p.day)};}
export function localDate(value){if(!value)return '';const d=new Date(value);return Number.isNaN(d.getTime())?'':d.toLocaleDateString('en-PH',{timeZone:CAMPUS_TIME_ZONE,month:'short',day:'numeric',year:'numeric'});}
export function localTime(value){if(!value)return '';const d=new Date(value);return Number.isNaN(d.getTime())?'':d.toLocaleTimeString('en-PH',{timeZone:CAMPUS_TIME_ZONE,hour:'numeric',minute:'2-digit'});}
export function localDateTime(value){return `${localDate(value)} · ${localTime(value)}`;}
export function toInputDate(value){if(!value)return '';const d=new Date(value);if(Number.isNaN(+d))return '';const p=Object.fromEntries(new Intl.DateTimeFormat('en-PH',{timeZone:CAMPUS_TIME_ZONE,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(d).map(x=>[x.type,x.value]));return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;}
export function uploadData(form){return new FormData(form);}
