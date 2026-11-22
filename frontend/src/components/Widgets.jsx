import React,{useCallback,useEffect,useState} from 'react';
import {RefreshCcw,CalendarClock,Inbox,LoaderCircle} from 'lucide-react';
import {api} from '../lib/api';
export function useLoad(url,dependency=''){
 const [data,setData]=useState(null),[loading,setLoading]=useState(true),[error,setError]=useState(''),[tick,setTick]=useState(0);
 const reload=useCallback(()=>setTick(t=>t+1),[]);
 useEffect(()=>{if(!url){setData(null);setLoading(false);return;}let active=true;setLoading(true);setError('');
  api(url).then(d=>{if(active)setData(d);}).catch(e=>{if(active)setError(e.message);}).finally(()=>{if(active)setLoading(false);});
  return()=>{active=false;};
 },[url,dependency,tick]);return {data,loading,error,reload,setData};
}
export function Loading({text='Loading campus information...'}){return <div className="state-card"><LoaderCircle size={25} className="spin"/><p>{text}</p></div>;}
export function ErrorState({message,retry}){return <div className="state-card"><RefreshCcw size={25}/><h3>Something went wrong</h3><p>{message}</p>{retry&&<button className="btn btn-primary" onClick={retry}><RefreshCcw size={16}/> Try again</button>}</div>;}
export function EmptyState({title='Nothing here yet',description='Check back soon for updates.',children}){return <div className="state-card"><Inbox size={29}/><h3>{title}</h3><p>{description}</p>{children}</div>;}
export function PageTitle({eyebrow,title,description,action}){return <div className="page-heading"><div><span className="eyebrow">{eyebrow}</span><h1>{title}</h1>{description&&<p>{description}</p>}</div>{action&&<div className="heading-action">{action}</div>}</div>}
export function StatusBadge({value}){return <span className={`status status-${(value||'pending').toLowerCase().replace(/ /g,'-')}`}>{value||'Pending'}</span>;}
export function Stat({value,label,icon:Icon}){return <div className="stat-tile">{Icon&&<span className="stat-icon"><Icon size={20}/></span>}<strong>{value??'—'}</strong><small>{label}</small></div>;}
export function Confirm({message,onYes,onNo}){return <div className="modal-backdrop" onClick={onNo}><div className="dialog" onClick={e=>e.stopPropagation()}><h3>Confirm action</h3><p>{message}</p><div className="actions"><button className="btn btn-secondary" onClick={onNo}>Go back</button><button className="btn btn-primary" onClick={onYes}>Confirm</button></div></div></div>}
export function Field({label,children,hint}){return <label className="field"><span>{label}</span>{children}{hint&&<small>{hint}</small>}</label>}
export function FormNotice({error}){return error?<p className="form-error" role="alert">{error}</p>:null;}
export function NiceDate({date}){if(!date)return null;return <span className="date-chip"><CalendarClock size={15}/>{new Date(date).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})}</span>}
