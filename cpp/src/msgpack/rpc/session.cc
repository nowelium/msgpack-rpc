//
// msgpack::rpc::session - MessagePack-RPC for C++
//
// Copyright (C) 2009-2010 FURUHASHI Sadayuki
//
//    Licensed under the Apache License, Version 2.0 (the "License");
//    you may not use this file except in compliance with the License.
//    You may obtain a copy of the License at
//
//        http://www.apache.org/licenses/LICENSE-2.0
//
//    Unless required by applicable law or agreed to in writing, software
//    distributed under the License is distributed on an "AS IS" BASIS,
//    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//    See the License for the specific language governing permissions and
//    limitations under the License.
//
#include "session.h"
#include "session_impl.h"
#include "future_impl.h"
#include "request_impl.h"
#include "exception_impl.h"
#include "message_sendable.h"
#include "cclog/cclog.h"

#include "transport/base.h"
#include "transport/tcp.h"

namespace msgpack {
namespace rpc {


session_impl::session_impl(const address& to_address,
		const transport_option& topt,
		const address& self_address,
		dispatcher* dp, loop lo) :
	m_addr(to_address),
	m_self_addr(self_address),
	m_tran(new transport::tcp(this, topt)),
	m_dp(dp),
	m_loop(lo),
	m_msgid_rr(0),  // FIXME rand()?
	m_timeout(5)  // FIXME
{ }

session_impl::~session_impl() { }


future session_impl::send_request_impl(msgid_t msgid, vrefbuffer* vbuf, shared_zone z)
{
	shared_future f(new future_impl(shared_from_this(), m_loop));
	m_reqtable.insert(msgid, f);

	m_tran->send_data(vbuf, z);

	return future(f);
}

future session_impl::send_request_impl(msgid_t msgid, sbuffer* sbuf)
{
	shared_future f(new future_impl(shared_from_this(), m_loop));
	m_reqtable.insert(msgid, f);

	m_tran->send_data(sbuf);

	return future(f);
}

void session_impl::send_notify_impl(vrefbuffer* vbuf, shared_zone z)
{
	m_tran->send_data(vbuf, z);
}

void session_impl::send_notify_impl(sbuffer* sbuf)
{
	m_tran->send_data(sbuf);
}

msgid_t session_impl::next_msgid()
{
	// FIXME __sync_add_and_fetch
	return __sync_add_and_fetch(&m_msgid_rr, 1);
}


void session_impl::step_timeout()
{
	LOG_TRACE("step_timeout");
	std::vector<shared_future> timedout;
	m_reqtable.step_timeout(&timedout);
	if(timedout.empty()) { return ;}
	for(std::vector<shared_future>::iterator it(timedout.begin()),
			it_end(timedout.end()); it != it_end; ++it) {
		shared_future& f = *it;
		f->set_result(object(), TIMEOUT_ERROR, auto_zone());
	}
}

void session_impl::on_connect_failed()
{
	std::vector<shared_future> all;
	m_reqtable.take_all(&all);
	for(std::vector<shared_future>::iterator it(all.begin()),
			it_end(all.end()); it != it_end; ++it) {
		shared_future& f = *it;
		f->set_result(object(), CONNECT_ERROR, auto_zone());
	}
}

void session_impl::on_message(
		message_sendable* ms,
		object msg, auto_zone z)
{
	msg_rpc rpc;
	msg.convert(&rpc);

	switch(rpc.type) {
	case REQUEST: {
			msg_request<object, object> req;
			msg.convert(&req);
			on_request(ms, req.msgid, req.method, req.param, z);
		}
		break;

	case RESPONSE: {
			msg_response<object, object> res;
			msg.convert(&res);
			on_response(ms, res.msgid, res.result, res.error, z);
		}
		break;

	case NOTIFY: {
			msg_notify<object, object> req;
			msg.convert(&req);
			on_notify(ms, req.method, req.param, z);
		}
		break;

	default:
		throw msgpack::type_error();
	}
}

void session_impl::on_response(
		message_sendable* ms, msgid_t msgid,
		object result, object error, auto_zone z)
{
	LOG_TRACE("response result=",result," error=",error);
	shared_future f = m_reqtable.take(msgid);
	if(!f) {
		LOG_DEBUG("no entry on request table for msgid=",msgid);
		return;
	}
	f->set_result(result, error, z);
}

void session_impl::on_request(
		message_sendable* ms, msgid_t msgid,
		object method, object param, auto_zone z)
{
	LOG_TRACE("request method=",method," msgid=",msgid);
	shared_request sreq(new request_impl(
			ms->shared_from_this(), msgid,
			session(shared_from_this()),
			method, param, z));
	m_dp->dispatch(request(sreq));
}

void session_impl::on_notify(
		message_sendable* ms,
		object method, object param, auto_zone z)
{
	LOG_TRACE("notify method=",method);
	shared_request sreq(new request_impl(
			shared_message_sendable(), 0,
			session(shared_from_this()),
			method, param, z));
	m_dp->dispatch(request(sreq));
}


const address& session::get_address() const
	{ return m_pimpl->get_address(); }

const address& session::get_self_address() const
	{ return m_pimpl->get_self_address(); }

loop session::get_loop()
	{ return m_pimpl->get_loop(); }

void session::set_timeout(unsigned int sec)
	{ m_pimpl->set_timeout(sec); }

unsigned int session::get_timeout() const
	{ return m_pimpl->get_timeout(); }

future session::send_request_impl(msgid_t msgid, vrefbuffer* vbuf, shared_zone z)
	{ return m_pimpl->send_request_impl(msgid, vbuf, z); }

future session::send_request_impl(msgid_t msgid, sbuffer* sbuf)
	{ return m_pimpl->send_request_impl(msgid, sbuf); }

void session::send_notify_impl(vrefbuffer* vbuf, shared_zone z)
	{ return m_pimpl->send_notify_impl(vbuf, z); }

void session::send_notify_impl(sbuffer* sbuf)
	{ return m_pimpl->send_notify_impl(sbuf); }

msgid_t session::next_msgid()
	{ return m_pimpl->next_msgid(); }


}  // namespace rpc
}  // namespace msgpack

