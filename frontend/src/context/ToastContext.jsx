import React,{createContext,useCallback,useContext,useEffect,useMemo,useRef,useState} from 'react';
import {CheckCircle2,AlertCircle,X} from 'lucide-react';
const C=createContext(null);
export function ToastProvider({children}){
 const [notice,setNotice]=useState(null);
 const lastNotice=useRef(null);
 const sequence=useRef(0);
 const flash=useCallback((message,type='success')=>{
  const now=Date.now();
  if(lastNotice.current?.message===message&&lastNotice.current?.type===type&&now-lastNotice.current.time<1000)return;
  lastNotice.current={message,type,time:now};
  setNotice({message,type,id:++sequence.current});
 },[]);
 useEffect(()=>{
  if(!notice||notice.type==='error')return undefined;
  const timer=setTimeout(()=>setNotice(current=>current?.id===notice.id?null:current),4500);
  return()=>clearTimeout(timer);
 },[notice]);
 const value=useMemo(()=>({flash}),[flash]);
 return <C.Provider value={value}>{children}{notice&&<div className={`toast ${notice.type==='error'?'toast-error':''}`} role="status">{notice.type==='error'?<AlertCircle size={20}/>:<CheckCircle2 size={20}/>}<span>{notice.message}</span><button className="icon-btn" onClick={()=>setNotice(null)} aria-label="Close"><X size={16}/></button></div>}</C.Provider>;
}
export const useToast=()=>useContext(C);
